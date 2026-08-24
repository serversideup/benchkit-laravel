<?php

namespace Tests\Feature\Benchmarks;

use App\Support\BenchmarkStages;
use App\Support\GeneratorSession;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class HttpBenchmarkTest extends TestCase
{
    use UsesFakeResultsPath;
    use UsesFakeRunPath;

    /**
     * Resolving the stage is what picks a reachable target and records the
     * load it will be tested with; the run process does this immediately
     * before launching the load generator.
     *
     * @param  array<string, mixed>  $settings
     * @return array{command: string, collect: ?string}
     */
    protected function resolveHttpStage(array $settings = []): array
    {
        return (new BenchmarkStages)->resolve('http', $settings);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return json_decode(file_get_contents($this->resultsPath.'/http-meta.json'), true);
    }

    public function test_the_http_stage_records_the_resolved_target(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame('http://localhost:8080', $meta['target']);
        $this->assertSame('loopback', $meta['mode']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_the_http_stage_uses_requested_load_settings(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage(['http_duration' => 30, 'http_connections' => 100]);

        $meta = $this->meta();
        $this->assertSame(30, $meta['duration_seconds']);
        $this->assertSame(100, $meta['connections']);
    }

    public function test_the_http_stage_falls_back_to_the_standard_load(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame(config('benchmark.http.duration_seconds'), $meta['duration_seconds']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_the_http_stage_records_the_simulated_io_delay(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();
        $this->assertSame(config('benchmark.http.io_ms'), $this->meta()['io_ms']);

        $this->resolveHttpStage(['http_io_ms' => 250]);
        $this->assertSame(250, $this->meta()['io_ms']);
    }

    public function test_the_http_stage_command_warms_up_and_carries_the_io_delay(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $command = $this->resolveHttpStage(['http_io_ms' => 150])['command'];

        // The io route carries the delay as a query param; the others do not.
        $this->assertStringContainsString('/bench/io?ms=150', $command);
        // Every route is warmed (discarded to /dev/null) before it is measured.
        $this->assertStringContainsString('> /dev/null', $command);
    }

    public function test_the_http_stage_rejects_redirecting_targets(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://localhost:8443'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not reach itself over HTTP');

        $this->resolveHttpStage();
    }

    public function test_the_http_stage_skips_targets_that_answer_200_without_the_sentinel_body(): void
    {
        Http::fake([
            'http://localhost:8080/*' => Http::response('', 200),
            'https://localhost:8443/*' => Http::response('BenchKit OK', 200),
        ]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame('https://localhost:8443', $meta['target']);
        $this->assertSame('loopback', $meta['mode']);
    }

    public function test_the_http_stage_fails_when_no_target_serves_the_sentinel_body(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->expectException(RuntimeException::class);

        $this->resolveHttpStage();
    }

    public function test_http_results_returns_no_results_when_no_run_has_happened(): void
    {
        $this->getJson('/http/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_http_summary_command_prints_detailed_metrics_from_oha_json(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => [
                'successRate' => 1.0,
                'requestsPerSec' => 5623.4,
                'total' => 10.0,
                'average' => 0.0089,
                'fastest' => 0.0012,
                'slowest' => 0.0234,
                'sizePerSec' => 12960890,
                'totalData' => 1804000,
            ],
            'latencyPercentiles' => ['p50' => 0.0085, 'p90' => 0.0121, 'p95' => 0.014, 'p99' => 0.018],
            'statusCodeDistribution' => ['200' => 56238],
            'errorDistribution' => ['aborted due to deadline' => 50],
        ]));

        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Requests/sec   5,623.4')
            ->expectsOutputToContain('p95 14.00')
            ->expectsOutputToContain('200: 56,238')
            ->expectsOutputToContain('aborted due to deadline: 50');
    }

    public function test_http_summary_command_is_quiet_when_a_route_has_no_results(): void
    {
        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static.');
    }

    public function test_http_summary_command_discards_runs_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => [
                'successRate' => 1.0,
                'requestsPerSec' => 175807.1,
                'total' => 10.0,
                'totalData' => 0,
            ],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1758717],
        ]));

        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static.');
    }

    public function test_http_results_parses_oha_output_per_route(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'duration_seconds' => 10,
            'connections' => 50,
            'io_ms' => 100,
        ]));

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56],
            'latencyPercentiles' => ['p50' => 0.010, 'p95' => 0.025, 'p99' => 0.040],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        File::put($this->resultsPath.'/http-db-read.json', json_encode([
            'summary' => ['successRate' => 0.99, 'requestsPerSec' => 456.78],
            'latencyPercentiles' => ['p50' => 0.050, 'p95' => 0.120, 'p99' => 0.300],
            'statusCodeDistribution' => ['200' => 4500, '500' => 45],
        ]));

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.mode', 'loopback');
        $response->assertJsonPath('http_results.target', 'http://localhost:8080');
        $response->assertJsonPath('http_results.io_ms', 100);
        $response->assertJsonPath('http_results.routes.static.requests_per_second', 1234.6);
        $response->assertJsonPath('http_results.routes.static.p95_ms', 25);
        $response->assertJsonPath('http_results.routes.static.total_requests', 12345);
        $response->assertJsonPath('http_results.routes.db_read.p99_ms', 300);
        $response->assertJsonPath('http_results.routes.db_read.total_requests', 4545);
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }

    public function test_http_results_excludes_routes_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        File::put($this->resultsPath.'/http-json.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 155257.6, 'totalData' => 0],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1553175],
        ]));

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.routes.static.requests_per_second', 1234.6);
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedMetaAndOneRoute(array $overrides = [], ?float $ioRequestsPerSecond = null): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode(array_merge([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'duration_seconds' => 10,
            'connections' => 50,
            'io_ms' => 100,
        ], $overrides)));

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        if ($ioRequestsPerSecond !== null) {
            File::put($this->resultsPath.'/http-io.json', json_encode([
                'summary' => ['successRate' => 1.0, 'requestsPerSec' => $ioRequestsPerSecond, 'totalData' => 40000],
                'latencyPercentiles' => ['p50' => 0.105],
                'statusCodeDistribution' => ['200' => (int) $ioRequestsPerSecond * 10],
            ]));
        }
    }

    /**
     * Holding more connections open than the server has workers is what a
     * saturation test is for, so this is recorded as a property of the load
     * rather than as a fault. What it changes is how the latency figures read:
     * they include time spent queued.
     */
    public function test_http_results_record_that_the_load_exceeded_the_worker_count(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 50, 'workers' => 20]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.workers', 20);
        $response->assertJsonPath('http_results.oversubscribed', true);
    }

    public function test_http_results_do_not_record_oversubscription_when_the_load_fits(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 20, 'workers' => 50]);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.oversubscribed', false);
    }

    /**
     * The claim "the worker pool was the ceiling" needs evidence, and the I/O
     * route is the only place it can be had: its service time is a known sleep,
     * so workers x (1000/io_ms) is a real limit. 20 workers at 100ms caps it at
     * 200 req/s, and 190 is at that cap.
     */
    public function test_http_results_flag_a_pool_ceiling_the_io_route_actually_reached(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 50, 'workers' => 20], ioRequestsPerSecond: 190.0);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.pool_limited', true);
    }

    /**
     * The regression this replaced: `connections > workers` compares two
     * settings and is evidence of nothing. On a two-core box with twenty
     * workers it reported every run as pool-bound while the actual ceiling was
     * the CPU — advising more workers, which would have made latency worse and
     * throughput no better. An I/O route far below its computed limit is a run
     * that was capped by something else.
     */
    public function test_http_results_do_not_blame_the_pool_when_the_io_route_is_nowhere_near_it(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 50, 'workers' => 20], ioRequestsPerSecond: 60.0);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.oversubscribed', true);
        $response->assertJsonPath('http_results.pool_limited', false);
    }

    /**
     * A managed platform may expose no worker count at all, and a run without
     * the I/O route has nothing to compute a ceiling from. Either way that
     * reads as unknown, not as "fits comfortably".
     */
    public function test_http_results_report_pool_limited_as_unknown_without_evidence(): void
    {
        $this->seedMetaAndOneRoute(['workers' => null]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.workers', null);
        $response->assertJsonPath('http_results.pool_limited', null);
    }

    public function test_http_results_report_pool_limited_as_unknown_without_the_io_route(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 50, 'workers' => 20]);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.pool_limited', null);
    }

    /**
     * The worker ceiling used to be recorded under an FPM-specific name. A
     * results directory written by an older build is still worth reading.
     */
    public function test_http_results_read_the_worker_ceiling_from_the_previous_meta_key(): void
    {
        $this->seedMetaAndOneRoute(['connections' => 50, 'fpm_max_children' => 20]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.workers', 20);
        $response->assertJsonPath('http_results.oversubscribed', true);
    }

    /**
     * Both container-internal ports call themselves "loopback", so a TLS run
     * and a plaintext one describe themselves identically without this.
     */
    public function test_http_results_record_whether_the_target_used_tls(): void
    {
        $this->seedMetaAndOneRoute(['tls' => true]);

        $this->getJson('/http/results')->assertOk()->assertJsonPath('http_results.tls', true);
    }

    public function test_the_http_stage_records_a_self_generator(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $generator = $this->meta()['generator'];
        $this->assertSame('self', $generator['mode']);
        $this->assertNull($generator['rtt_ms']);
        $this->assertNull($generator['source_ip']);
    }

    /**
     * A meta file without a generator block was written before external mode
     * existed, which provably makes it a self-test — not an unknown.
     */
    public function test_http_results_default_the_generator_to_self_for_older_meta(): void
    {
        $this->seedMetaAndOneRoute();

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator.mode', 'self');
    }

    public function test_http_results_derive_self_rtt_from_the_fastest_request(): void
    {
        $this->seedMetaAndOneRoute();

        File::put($this->resultsPath.'/http-json.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 900.0, 'fastest' => 0.0004, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010],
            'statusCodeDistribution' => ['200' => 9000],
        ]));

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator.rtt_ms', 0.4);
    }

    public function test_http_results_pass_through_an_external_generator_block(): void
    {
        $this->seedMetaAndOneRoute(['generator' => [
            'mode' => 'external',
            'rtt_ms' => 1.8,
            'source_ip' => '203.0.113.7',
            'oha_version' => '1.4.5',
            'host' => 'generator-box',
        ]]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.generator.mode', 'external');
        $response->assertJsonPath('http_results.generator.rtt_ms', 1.8);
        $response->assertJsonPath('http_results.generator.oha_version', '1.4.5');
    }

    /**
     * A closed-loop generator cannot exceed connections / round-trip-floor.
     * 50 connections over a 60ms floor caps at ~833 req/s; a run reporting
     * 820 landed at that ceiling, so the path — not the server — was the
     * limit. 400 req/s against the same floor is a server being measured.
     */
    public function test_http_results_flag_a_run_the_generator_capped(): void
    {
        $this->seedMetaAndOneRoute();

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 820.0, 'fastest' => 0.060, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.061],
            'statusCodeDistribution' => ['200' => 8200],
        ]));

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', true);
    }

    public function test_http_results_do_not_blame_the_generator_when_the_server_was_the_limit(): void
    {
        $this->seedMetaAndOneRoute();

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 400.0, 'fastest' => 0.060, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.125],
            'statusCodeDistribution' => ['200' => 4000],
        ]));

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', false);
    }

    public function test_http_results_report_generator_bound_as_unknown_without_a_latency_floor(): void
    {
        // The seeded static route carries no `fastest`, so there is no floor
        // to compute a ceiling from.
        $this->seedMetaAndOneRoute();

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', null);
    }

    public function test_the_external_stage_arms_the_pairing_and_waits(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $session = (new GeneratorSession)->create('https://public.example.com', 'https://public.example.com');
        $run = (new RunState)->start(['http' => true], ['http'], null);

        // A stale route file from a previous run must not satisfy the wait.
        File::put($this->resultsPath.'/http-static.json', '{}');

        $stage = $this->resolveHttpStage(['http_generator' => 'external', 'http_duration' => 10]);

        $this->assertStringContainsString('benchmark:await-generator', $stage['command']);
        $this->assertFileDoesNotExist($this->resultsPath.'/http-static.json');

        $meta = $this->meta();
        $this->assertSame('https://public.example.com', $meta['target']);
        $this->assertSame('external', $meta['mode']);
        $this->assertSame('external', $meta['generator']['mode']);

        $armed = (new GeneratorSession)->current();
        $this->assertSame(GeneratorSession::STATUS_ARMED, $armed['status']);
        $this->assertSame($run['id'], $armed['run_id']);
        // The work fragment is rendered from the run's settings, not the
        // pairing's — the single source of truth end to end.
        $this->assertStringContainsString('oha -z 10s -c 50 --redirect 0 --insecure', $armed['work']);
        $this->assertStringContainsString('oha -z 3s', $armed['work']);
    }

    public function test_the_external_stage_fails_actionably_without_a_pairing(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no generator is paired');

        $this->resolveHttpStage(['http_generator' => 'external']);
    }

    /**
     * The io route is closed-loop-bound by design — its known sleep is the
     * whole point — and its ceiling already has a check (pool_limited).
     * Reading its sleep as network distance would flag every healthy run.
     */
    public function test_generator_bound_ignores_the_io_route(): void
    {
        $this->seedMetaAndOneRoute(['workers' => 100], ioRequestsPerSecond: 490.0);

        File::put($this->resultsPath.'/http-io.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 490.0, 'fastest' => 0.100, 'totalData' => 40000],
            'latencyPercentiles' => ['p50' => 0.102],
            'statusCodeDistribution' => ['200' => 4900],
        ]));

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', null);
    }
}
