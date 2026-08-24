<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorSession;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class AwaitGeneratorLoadTest extends TestCase
{
    use UsesFakeResultsPath;
    use UsesFakeRunPath;

    /**
     * An armed pairing, as the external HTTP stage leaves it just before the
     * waiting command becomes the stage.
     *
     * @return array<string, mixed>
     */
    protected function armed(): array
    {
        $session = (new GeneratorSession)->create('https://bench.example.com', 'https://bench.example.com');
        $run = (new RunState)->start(['http' => true], ['http'], null);
        (new RunState)->claim(getmypid());

        (new HttpBenchmarkResults)->writeMeta(
            ['url' => 'https://bench.example.com', 'mode' => 'external'],
            10, 50, 100, 16,
            ['mode' => 'external', 'rtt_ms' => null, 'source_ip' => null, 'oha_version' => null, 'host' => null],
        );

        return (new GeneratorSession)->arm($run['id'], '# work');
    }

    protected function receiveRoute(string $key, float $requestsPerSecond = 1234.56): void
    {
        File::put((new HttpBenchmarkResults)->routePath($key), json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => $requestsPerSecond, 'total' => 10.0, 'fastest' => 0.002, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010, 'p95' => 0.025, 'p99' => 0.040],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        (new GeneratorSession)->recordReceived($key, round($requestsPerSecond, 1), '203.0.113.7');
    }

    public function test_it_completes_once_every_route_has_arrived(): void
    {
        $this->armed();

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $key) {
            $this->receiveRoute($key);
        }

        $this->artisan('benchmark:await-generator', ['--timeout' => 5])
            ->assertExitCode(0)
            ->expectsOutputToContain('Received /bench/static from 203.0.113.7 — 1,234.6 req/s')
            ->expectsOutputToContain('Requests/sec')
            ->expectsOutputToContain('External load test complete — all routes received.');

        $this->assertSame(GeneratorSession::STATUS_DONE, (new GeneratorSession)->current()['status']);
    }

    /**
     * A partial delivery is kept: the stage exits successfully so the
     * snapshot records the routes that did arrive, and says so out loud.
     */
    public function test_a_partial_delivery_is_kept_when_the_generator_stops(): void
    {
        $this->armed();
        $this->receiveRoute('static');
        $this->receiveRoute('json');

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('The generator stopped after 2 of 4 routes — keeping the partial results.');
    }

    public function test_it_fails_when_nothing_ever_arrives(): void
    {
        $this->armed();

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('No generator delivered results in time.');

        $this->assertSame(GeneratorSession::STATUS_ERROR, (new GeneratorSession)->current()['status']);
    }

    public function test_it_reprints_the_pairing_command_while_nothing_is_connected(): void
    {
        $session = $this->armed();

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->expectsOutputToContain('curl -fsSL https://bench.example.com/bench/generator/'.$session['token'].'/script | sh');
    }

    /**
     * A generator that pairs after the stage armed still gets its details
     * into the meta file — the stage wrote it before the handshake existed.
     */
    public function test_a_late_handshake_is_merged_into_the_meta(): void
    {
        $this->armed();
        (new GeneratorSession)->recordHandshake([
            'oha_version' => '1.4.5',
            'cores' => 8,
            'host' => 'generator-box',
            'rtt_ms' => 1.83,
            'source_ip' => '203.0.113.7',
        ]);
        $this->receiveRoute('static');

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('Generator connected from 203.0.113.7 — generator-box · oha 1.4.5 · 8 cores · RTT 1.83ms.');

        $meta = json_decode(File::get((new HttpBenchmarkResults)->metaPath()), true);
        $this->assertSame(1.83, $meta['generator']['rtt_ms']);
        $this->assertSame('generator-box', $meta['generator']['host']);
        $this->assertSame('external', $meta['generator']['mode']);
    }

    public function test_rejected_uploads_are_explained_in_the_console(): void
    {
        $this->armed();
        (new GeneratorSession)->recordRejection('static', 'the run transferred zero bytes', '203.0.113.7');
        $this->receiveRoute('json');

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->expectsOutputToContain('Rejected an upload for static from 203.0.113.7: the run transferred zero bytes');
    }

    public function test_it_fails_when_no_pairing_exists(): void
    {
        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('The generator pairing disappeared');
    }
}
