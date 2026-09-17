<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\BenchmarkStages;
use App\Support\GeneratorSession;
use App\Support\Http\LoadProfile;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\SeedsHttpResults;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class HttpBenchmarkTest extends TestCase
{
    use SeedsHttpResults;
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
        $this->assertNotEmpty($meta['levels']);
    }

    /**
     * The concurrency levels are not a setting. They are derived from what the
     * machine reports, because a fixed number is twelve times oversubscribed
     * on a small box and barely warm on a large one — and those are not the
     * same test.
     */
    public function test_the_http_stage_sizes_the_load_from_the_machine(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $levels = $this->meta()['levels'];

        $this->assertSame(array_keys(HttpBenchmarkResults::ROUTES), array_keys($levels));

        foreach ($levels as $route => $routeLevels) {
            $this->assertSame(1, $routeLevels[0], "{$route} must start at one connection — that is where an honest round-trip floor is read from.");
            $this->assertSame($routeLevels, array_values(array_unique($routeLevels)));
            $this->assertSame($routeLevels, collect($routeLevels)->sort()->values()->all());
        }
    }

    public function test_the_http_stage_records_the_levels_it_will_measure(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        // Seeded with what the host's cores and workers imply, and replaced
        // per route once the probe has measured what the server really costs.
        // Recorded rather than recomputed downstream, because nothing reading
        // a run can know what the probe found.
        $this->assertSame(
            LoadProfile::levels($this->meta()['cores'], $this->meta()['workers']),
            $this->meta()['levels']['static'],
        );
    }

    public function test_the_http_stage_records_the_simulated_io_delay(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();
        $this->assertSame(config('benchmark.http.io_ms'), $this->meta()['io_ms']);

        $this->resolveHttpStage(['http_io_ms' => 250]);
        $this->assertSame(250, $this->meta()['io_ms']);
    }

    public function test_the_http_stage_hands_the_load_to_its_own_driver(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        // The load has a step in the middle that has to be computed — the
        // response-time pass offers a rate derived from what the sweep proved
        // the server can hold — so it cannot be a shell chain written up front.
        $this->assertStringContainsString('benchmark:http-load', $this->resolveHttpStage()['command']);
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
        File::put($this->resultsPath.'/http-static-c20.json', json_encode([
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

        $this->artisan('benchmark:http-summary', ['slot' => 'static-c20'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Requests/sec   5,623.4')
            ->expectsOutputToContain('p95 14.00')
            ->expectsOutputToContain('200: 56,238')
            ->expectsOutputToContain('aborted due to deadline: 50');
    }

    public function test_http_summary_command_is_quiet_when_a_route_has_no_results(): void
    {
        $this->artisan('benchmark:http-summary', ['slot' => 'static-c20'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static-c20.');
    }

    public function test_http_summary_command_discards_runs_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-static-c20.json', json_encode([
            'summary' => [
                'successRate' => 1.0,
                'requestsPerSec' => 175807.1,
                'total' => 10.0,
                'totalData' => 0,
            ],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1758717],
        ]));

        $this->artisan('benchmark:http-summary', ['slot' => 'static-c20'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static-c20.');
    }

    public function test_http_results_report_throughput_and_response_time_from_different_measurements(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute([1, 20]),
        ]));

        $this->writeOha($this->resultsPath.'/http-static-c1.json', 100.0, 1);
        $this->writeOha($this->resultsPath.'/http-static-c20.json', 1234.56, 20);
        // The response-time pass runs open-loop below the peak, so its p50 is
        // what a visitor experiences rather than the queue the sweep measured.
        $this->writeOha($this->resultsPath.'/http-static-latency.json', 864.0, 4);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.mode', 'loopback');
        $response->assertJsonPath('http_results.target', 'http://localhost:8080');
        $response->assertJsonPath('http_results.io_ms', 100);
        $response->assertJsonPath('http_results.routes.static.throughput.requests_per_second', 1234.6);
        $response->assertJsonPath('http_results.routes.static.throughput.concurrency', 20);
        $response->assertJsonPath('http_results.routes.static.throughput.total_requests', 7407);
        $response->assertJsonPath('http_results.routes.static.latency.achieved_rps', 864);
        $response->assertJsonPath('http_results.routes.static.latency.corrected', true);
        $this->assertCount(2, $response->json('http_results.routes.static.curve'));
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }

    public function test_a_route_with_no_response_time_pass_still_reports_its_throughput(): void
    {
        $this->seedMetaAndOneRoute(['workers' => 20]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.routes.static.latency', null);
        $this->assertNotNull($response->json('http_results.routes.static.throughput'));
    }

    public function test_http_results_excludes_routes_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute([20]),
        ]));

        File::put($this->resultsPath.'/http-static-c20.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        File::put($this->resultsPath.'/http-json-c20.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 155257.6, 'totalData' => 0],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1553175],
        ]));

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.routes.static.throughput.requests_per_second', 1234.6);
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    /**
     * A full sweep of the io route at the given levels, which is the route the
     * pool ceiling is computed from.
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<int, float>  $rpsByConcurrency
     */
    /**
     * What the run could reach from where the load came from, and what it did.
     *
     * Every figure is measured or arithmetic on measured values — there is no
     * projected concurrency here, because rate x response time is the closed
     * loop's own identity and returns a number already offered.
     */
    public function test_http_results_publish_what_the_run_could_reach(): void
    {
        $this->seedReach(transportRttMs: 1.5, idleMs: 3.0, topConnections: 512, peakRps: 170000.0);

        $reach = $this->getJson('/http/results')->assertOk()->json('http_results.reach');

        $this->assertSame('static', $reach['route']);
        $this->assertSame(512, $reach['connections']);
        $this->assertEquals(3.0, $reach['idle_ms']);
        $this->assertEquals(1.5, $reach['transport_rtt_ms']);
        $this->assertEquals(0.5, $reach['network_share'], 'Half of every request never reached the server.');
        $this->assertEquals(333.3, $reach['rps_per_connection']);
        $this->assertEquals(170649.6, $reach['offered_rps_ceiling']);
        $this->assertSame('benchkit', $reach['capped_by']);
    }

    public function test_http_results_say_when_the_generator_ran_out_of_connections(): void
    {
        $this->seedReach(transportRttMs: 1.5, idleMs: 3.0, topConnections: 192, peakRps: 64000.0, fdLimit: 256);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.reach.capped_by', 'generator');
    }

    public function test_http_results_say_nothing_capped_a_sweep_that_stopped_short(): void
    {
        $this->seedReach(transportRttMs: 1.5, idleMs: 3.0, topConnections: 40, peakRps: 13000.0);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.reach.capped_by', null);
    }

    /**
     * Busy against offered is the whole discriminator: a path-bound run keeps
     * every connection carrying a request, a run bound by the machine driving
     * the load does not.
     */
    public function test_http_results_show_connections_that_sat_idle(): void
    {
        $this->seedReach(transportRttMs: 1.5, idleMs: 3.0, topConnections: 512, peakRps: 170000.0, idleConnections: true);

        $reach = $this->getJson('/http/results')->assertOk()->json('http_results.reach');

        $this->assertLessThan($reach['connections'] * 0.6, $reach['busy_connections']);
    }

    public function test_a_self_test_reports_no_distance(): void
    {
        $this->seedReach(transportRttMs: 0.0, idleMs: 3.0, topConnections: 40, peakRps: 13000.0);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.reach.network_share', fn ($share): bool => (float) $share === 0.0);
    }

    public function test_http_results_no_longer_publish_a_predicted_saturating_concurrency(): void
    {
        $this->seedMetaAndOneRoute(['workers' => 20]);

        $http = $this->getJson('/http/results')->assertOk()->json('http_results');

        $this->assertArrayNotHasKey('required_concurrency', $http);
    }

    public function test_the_http_stage_records_the_warmup_it_will_run(): void
    {
        config(['benchmark.http.sweep.warmup_seconds' => 5]);

        $this->resolveHttpStage(['http' => true]);

        $this->assertSame(5, $this->meta()['warmup_seconds']);
    }

    /**
     * A sweep with a known idle latency at one connection and a known top
     * level, so reach() has both ends of the thing it describes.
     */
    protected function seedReach(
        float $transportRttMs,
        float $idleMs,
        int $topConnections,
        float $peakRps,
        ?int $fdLimit = null,
        bool $idleConnections = false,
    ): void {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'https://bench.example.com',
            'mode' => 'app-url',
            'io_ms' => 100,
            'workers' => 20,
            'cores' => 4,
            'levels' => ['static' => [1, $topConnections]],
            'generator' => [
                'mode' => $transportRttMs > 0 ? 'external' : 'self',
                'transport_rtt_ms' => $transportRttMs,
                'fd_limit' => $fdLimit,
            ],
        ]));

        $this->writeOha($this->resultsPath.'/http-static-c1.json', 1000.0, 1, null, $idleMs / 1000);
        $this->writeOha(
            $this->resultsPath.'/http-static-c'.$topConnections.'.json',
            $peakRps,
            $topConnections,
            null,
            $idleConnections ? ($topConnections * 0.5) / $peakRps : null,
        );
    }

    /**
     * Levels are recorded per route, because the probe sizes them from each
     * route's own service time. Most fixtures want the same ladder everywhere.
     *
     * @param  array<int, int>  $levels
     * @return array<string, array<int, int>>
     */
    protected function levelsForEveryRoute(array $levels): array
    {
        return array_fill_keys(array_keys(HttpBenchmarkResults::ROUTES), $levels);
    }

    protected function seedSweep(array $overrides, array $rpsByConcurrency): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode(array_merge([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute(array_keys($rpsByConcurrency)),
        ], $overrides)));

        foreach ($rpsByConcurrency as $connections => $rps) {
            $this->writeOha($this->resultsPath.'/http-io-c'.$connections.'.json', $rps, $connections);
        }
    }

    protected function seedMetaAndOneRoute(array $overrides = [], ?float $ioRequestsPerSecond = null): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode(array_merge([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute([20]),
        ], $overrides)));

        $this->writeOha($this->resultsPath.'/http-static-c20.json', 1234.56, 20);

        if ($ioRequestsPerSecond !== null) {
            $this->writeOha($this->resultsPath.'/http-io-c20.json', $ioRequestsPerSecond, 20);
        }
    }

    /**
     * The sweep replaces the run-level "was this oversubscribed" flag, which
     * compared two settings and produced no evidence. What matters now is
     * whether throughput ever stopped improving inside the range measured — a
     * number the sweep never saw flatten is a floor, not a maximum.
     */
    public function test_http_results_report_a_curve_that_flattened_as_saturated(): void
    {
        $this->seedSweep(['workers' => 20], [1 => 10.0, 20 => 198.0, 40 => 199.0]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.routes.io.throughput.saturated', true);
        // The maximum and the concurrency it happened at. Publishing the
        // knee's throughput under a heading that says "max" reported a route
        // peaking at 437 req/s as 417.
        $response->assertJsonPath('http_results.routes.io.throughput.requests_per_second', 199);
        $response->assertJsonPath('http_results.routes.io.throughput.concurrency', 40);
        // The cheapest level within a few percent of that peak, which is what
        // the response-time pass holds open. A different question, kept as a
        // different number.
        $response->assertJsonPath('http_results.routes.io.throughput.knee_concurrency', 20);
    }

    public function test_http_results_report_a_curve_still_climbing_as_a_floor(): void
    {
        $this->seedSweep(['workers' => 20], [1 => 10.0, 20 => 200.0, 40 => 400.0]);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.routes.io.throughput.saturated', false);
    }

    /**
     * The claim "the worker pool is the ceiling" needs evidence, and the I/O
     * route is the only place it can be had: its service time is a known
     * sleep, so workers x (1000/io_ms) is a real limit. Twenty workers at
     * 100ms caps it at 200 req/s.
     *
     * The sweep makes this far stronger than a single window could. It reports
     * the prediction next to where the curve actually bent, and both landing
     * on the worker count is a two-variable coincidence — which is what lets
     * the results page draw a line from arithmetic and have the measurement
     * land on it.
     */
    public function test_http_results_publish_the_predicted_pool_ceiling_beside_the_measurement(): void
    {
        $this->seedSweep(['workers' => 20], [1 => 10.0, 20 => 190.0, 40 => 191.0]);

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.pool_ceiling.predicted_rps', 200);
        $response->assertJsonPath('http_results.pool_ceiling.observed_rps', 191);
        $response->assertJsonPath('http_results.pool_ceiling.knee_concurrency', 20);
        $response->assertJsonPath('http_results.pool_ceiling.at_ceiling', true);
    }

    /**
     * The regression this replaced: `connections > workers` compares two
     * settings and is evidence of nothing. On a two-core box with twenty
     * workers it reported every run as pool-bound while the actual ceiling was
     * the CPU — advising more workers, which would have made latency worse and
     * throughput no better. An I/O route far below its computed limit was
     * capped by something else.
     */
    public function test_http_results_do_not_blame_the_pool_when_the_io_route_is_nowhere_near_it(): void
    {
        $this->seedSweep(['workers' => 20], [1 => 10.0, 20 => 60.0, 40 => 61.0]);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.pool_ceiling.at_ceiling', false);
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

    /**
     * A self-test has nothing to measure the round trip up front, so it is
     * taken afterwards from the single-connection level — where nothing is
     * queued. The old figure came from the fastest request inside a saturated
     * window, where even the quickest observation had waited behind something.
     */
    public function test_http_results_derive_self_rtt_from_the_uncontended_level(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute([1, 20]),
        ]));

        // One connection at 2,500 req/s is a 0.4ms round trip.
        $this->writeOha($this->resultsPath.'/http-static-c1.json', 2500.0, 1);
        $this->writeOha($this->resultsPath.'/http-static-c20.json', 12000.0, 20);

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
     * In a closed loop each connection holds one request at a time, so
     * concurrency = rate x mean response time is an identity, not a model. A
     * level where that does not hold had connections sitting idle, which is
     * the machine driving the load running out of capacity rather than the one
     * serving it.
     *
     * This replaced `rps >= connections / round-trip-floor`, which needed a
     * fixed connection count the sweep no longer has, and could not see a
     * generator that saturated its own CPU partway up.
     */
    public function test_http_results_flag_a_run_the_generator_capped(): void
    {
        $this->seedMetaAndOneRoute();

        // 400 req/s at a 25ms mean needs ten connections busy, not twenty:
        // half of them sat idle.
        File::put($this->resultsPath.'/http-static-c20.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 400.0, 'average' => 0.025, 'fastest' => 0.020, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.025],
            'statusCodeDistribution' => ['200' => 4000],
        ]));

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', true);
    }

    public function test_http_results_do_not_blame_the_generator_when_it_kept_its_connections_busy(): void
    {
        $this->seedMetaAndOneRoute();
        $this->writeOha($this->resultsPath.'/http-static-c20.json', 400.0, 20);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', false);
    }

    public function test_http_results_report_generator_bound_as_unknown_without_any_measurement(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'io_ms' => 100,
            'levels' => $this->levelsForEveryRoute([20]),
        ]));

        $this->getJson('/http/results')->assertNotFound();
    }

    public function test_the_external_stage_arms_the_pairing_and_waits(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $session = (new GeneratorSession)->create('https://public.example.com', 'https://public.example.com');
        $run = (new RunState)->start(['http' => true], ['http'], null);

        $stage = $this->resolveHttpStage(['http_generator' => 'external']);

        $this->assertStringContainsString('benchmark:await-generator', $stage['command']);

        // A stale result from a previous run must not satisfy the wait: in
        // external mode a file existing is the signal that it was uploaded now.
        $stale = $this->resultsPath.'/http-static-c'.$this->meta()['levels']['static'][0].'.json';
        File::put($stale, '{}');
        $this->resolveHttpStage(['http_generator' => 'external']);
        $this->assertFileDoesNotExist($stale);

        $meta = $this->meta();
        $this->assertSame('https://public.example.com', $meta['target']);
        $this->assertSame('external', $meta['mode']);
        $this->assertSame('external', $meta['generator']['mode']);

        $armed = (new GeneratorSession)->current();
        $this->assertSame(GeneratorSession::STATUS_ARMED, $armed['status']);
        $this->assertSame($run['id'], $armed['run_id']);
        // The work fragment is rendered from the run's settings, not the
        // pairing's — the single source of truth end to end.
        // Rendered from the run's own profile, not the pairing's, and running
        // whatever oha the generator has on its PATH.
        $this->assertStringContainsString('oha -z 6s -c 1 ', $armed['work']);
        $this->assertStringContainsString('--insecure', $armed['work']);
        $this->assertStringContainsString('oha -z 3s', $armed['work']);
        // Each measured window uploads under its own name, so the file, the
        // upload URL, and the pairing's record of what landed cannot disagree.
        $this->assertStringContainsString("upload 'static-c1'", $armed['work']);
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
    /**
     * The old detector had to exclude /bench/io: a route that sleeps is capped
     * by the sleep, so `rate >= connections / round-trip-floor` was true there
     * for every healthy run and the route had to be special-cased out.
     *
     * Little's law needs no such exemption. A sleeping route satisfies
     * concurrency = rate x mean response time exactly like any other, so the
     * check now covers all four routes and one fewer thing can go wrong.
     */
    public function test_the_generator_check_needs_no_exemption_for_the_sleeping_route(): void
    {
        $this->seedMetaAndOneRoute(['workers' => 100]);
        // 200 req/s at a 100ms mean is exactly twenty connections kept busy.
        $this->writeOha($this->resultsPath.'/http-io-c20.json', 200.0, 20);

        $this->getJson('/http/results')->assertOk()
            ->assertJsonPath('http_results.generator_bound', false);
    }
}
