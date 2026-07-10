<?php

namespace Tests\Feature\Runs;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

class StoreRunTest extends TestCase
{
    use UsesFakeResultsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('runs');
    }

    protected function defaultSettings(): array
    {
        return [
            'hardware' => false,
            'disk' => false,
            'geekbench' => false,
            'geekbench_version' => 6,
            'iperf' => false,
            'network' => false,
            'network_test_type' => 'ipv4',
            'http' => true,
            'php_database' => false,
            'php_mode' => 'full',
        ];
    }

    protected function writeHttpFixtures(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'standard',
            'duration_seconds' => 10,
            'connections' => 50,
        ]));

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['requestsPerSec' => 34.1, 'successRate' => 1.0],
            'latencyPercentiles' => ['p50' => 1.84974, 'p95' => 2.33328, 'p99' => 2.39664],
            'statusCodeDistribution' => ['200' => 251],
        ]));
    }

    public function test_storing_a_run_creates_a_snapshot_file_with_only_completed_stages(): void
    {
        $this->writeHttpFixtures();

        File::put($this->resultsPath.'/phpbench_results.csv', implode("\n", [
            'benchmark,subject,revs,its,mem_peak,best,mean,mode,worst,stdev,rstdev',
            'InsertBenchmark,benchDbFacadeInsertIndividual,10,5,100,90000,95500,95000,99000,100,1.0',
        ]));

        $response = $this->postJson('/runs', [
            'stages_completed' => ['http'],
            'settings' => $this->defaultSettings(),
        ])->assertCreated();

        $id = $response->json('run.id');
        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}-[a-z0-9]{4}$/', $id);

        Storage::disk('runs')->assertExists("{$id}.json");

        $snapshot = json_decode(Storage::disk('runs')->get("{$id}.json"), true);

        $this->assertSame('benchkit-run', $snapshot['type']);
        $this->assertSame(['http'], $snapshot['stages_completed']);
        $this->assertNotNull($snapshot['benchmarks']['http']);
        $this->assertNull($snapshot['benchmarks']['php'], 'A stale phpbench file must not leak into a run that did not execute the php stage.');
        $this->assertNull($snapshot['benchmarks']['yabs']);
        $this->assertSame(34.1, $snapshot['summary']['http_rps']);
    }

    public function test_cfspeedtest_logs_are_scrubbed_of_ip_addresses(): void
    {
        $this->writeHttpFixtures();

        $response = $this->postJson('/runs', [
            'stages_completed' => ['http', 'cfspeedtest'],
            'settings' => $this->defaultSettings(),
            'logs' => [
                'cfspeedtest' => [
                    'Asn: 33363',
                    'Ip: 174.102.225.192',
                    'Your IP is 174.102.225.192 today',
                    'Colo: ORD',
                ],
                'http' => ['Load testing /bench/static'],
            ],
        ])->assertCreated();

        $snapshot = json_decode(Storage::disk('runs')->get($response->json('run.id').'.json'), true);

        $this->assertSame(['Asn: 33363', 'Colo: ORD'], $snapshot['logs']['cfspeedtest']);
        $this->assertSame(['Load testing /bench/static'], $snapshot['logs']['http']);
    }

    public function test_logs_for_stages_that_did_not_complete_are_dropped(): void
    {
        $this->writeHttpFixtures();

        $response = $this->postJson('/runs', [
            'stages_completed' => ['http'],
            'settings' => $this->defaultSettings(),
            'logs' => [
                'http' => ['line'],
                'yabs' => ['stale line'],
            ],
        ])->assertCreated();

        $snapshot = json_decode(Storage::disk('runs')->get($response->json('run.id').'.json'), true);

        $this->assertArrayNotHasKey('yabs', $snapshot['logs']);
    }

    public function test_snapshot_includes_auto_label_and_provider(): void
    {
        $this->writeHttpFixtures();

        $response = $this->postJson('/runs', [
            'stages_completed' => ['http'],
            'settings' => $this->defaultSettings(),
            'preset' => 'custom',
            'provider' => 'DIGITALOCEAN-ASN',
        ])->assertCreated();

        $this->assertStringEndsWith('(Custom)', $response->json('run.meta.label'));

        $meta = $response->json('run.meta');

        $this->assertNotSame('', (string) $meta['label']);
        $this->assertSame('DIGITALOCEAN-ASN', $meta['provider']);
        $this->assertSame('ripe', $meta['provider_source']);
        $this->assertNull($meta['plan']);
        $this->assertNull($meta['datacenter']);
        $this->assertNull($meta['cost']);
    }

    public function test_remembered_hosting_details_are_stored_with_the_run(): void
    {
        $this->writeHttpFixtures();

        $response = $this->postJson('/runs', [
            'stages_completed' => ['http'],
            'settings' => $this->defaultSettings(),
            'provider' => 'DigitalOcean',
            'provider_source' => 'user',
            'plan' => 'Premium AMD 2GB',
            'datacenter' => 'NYC3',
            'cost' => '$24/mo',
        ])->assertCreated();

        $meta = $response->json('run.meta');

        $this->assertSame('DigitalOcean', $meta['provider']);
        $this->assertSame('user', $meta['provider_source']);
        $this->assertSame('Premium AMD 2GB', $meta['plan']);
        $this->assertSame('NYC3', $meta['datacenter']);
        $this->assertSame('$24/mo', $meta['cost']);
    }

    public function test_storing_requires_at_least_one_completed_stage(): void
    {
        $this->postJson('/runs', [
            'stages_completed' => [],
            'settings' => $this->defaultSettings(),
        ])->assertUnprocessable()->assertJsonValidationErrors('stages_completed');
    }

    public function test_storing_rejects_unknown_stages(): void
    {
        $this->postJson('/runs', [
            'stages_completed' => ['bogus'],
            'settings' => $this->defaultSettings(),
        ])->assertUnprocessable()->assertJsonValidationErrors('stages_completed.0');
    }

    public function test_storing_rejects_overlong_provider(): void
    {
        $this->postJson('/runs', [
            'stages_completed' => ['http'],
            'settings' => $this->defaultSettings(),
            'provider' => str_repeat('a', 121),
        ])->assertUnprocessable()->assertJsonValidationErrors('provider');
    }
}
