<?php

namespace Tests\Unit\Http;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\StepResult;
use Tests\TestCase;

/**
 * The two decisions that make a run comparable across wildly different hosts:
 * which concurrency levels to measure at, and how to read what came back.
 * Both are worth proving against constructed results, where a plateau can be
 * described exactly, rather than only against a real server, where it cannot.
 */
class LoadSweepTest extends TestCase
{
    /**
     * A result from a generator that kept every connection busy. In a closed
     * loop the mean response time is exactly connections / throughput, so the
     * helper derives it rather than inventing one.
     */
    protected function measured(float $rps, int $connections, ?array $statusCodes = null): StepResult
    {
        $average = $rps > 0 ? $connections / $rps : 0.0;

        return StepResult::fromOha([
            'summary' => [
                'requestsPerSec' => $rps,
                'average' => $average,
                'fastest' => $average / 2,
                'successRate' => 1.0,
                'total' => 6.0,
            ],
            'latencyPercentiles' => ['p50' => $average, 'p95' => $average * 2, 'p99' => $average * 3],
            'statusCodeDistribution' => $statusCodes ?? ['200' => max(1, (int) ($rps * 6))],
        ]);
    }

    /**
     * @param  array<int, float>  $rpsByConcurrency
     */
    protected function curve(array $rpsByConcurrency, string $route = 'static'): LoadCurve
    {
        $measurements = [];

        foreach ($rpsByConcurrency as $concurrency => $rps) {
            $measurements[] = ['concurrency' => $concurrency, 'result' => $this->measured($rps, $concurrency)];
        }

        return new LoadCurve($route, $measurements);
    }

    public function test_the_levels_bracket_both_the_cpu_and_the_pool_ceiling(): void
    {
        // Four cores, twenty workers: the CPU routes bend near 4 and the io
        // route bends at 20, so there are points around both.
        $this->assertSame([1, 4, 8, 20, 40, 80], LoadProfile::levels(4, 20));
    }

    public function test_a_pool_much_larger_than_the_core_count_still_measures_the_cpu_bend(): void
    {
        // The bug this guards: seeding from workers alone gives 1, 43, 85,
        // 170 — and the CPU routes bend at about six, so the measurement
        // jumps clean over it and reports the bend at 43.
        $levels = LoadProfile::levels(4, 85);

        $this->assertSame([1, 4, 8], array_slice($levels, 0, 3));
        $this->assertContains(85, $levels);
    }

    public function test_the_levels_scale_with_the_host_rather_than_being_fixed(): void
    {
        $this->assertSame([1, 2, 4, 8, 16], LoadProfile::levels(1, 4));
        $this->assertSame([1, 16, 32, 64, 128, 256], LoadProfile::levels(16, 64));
    }

    public function test_the_levels_fall_back_to_a_wide_spread_when_the_host_is_unknown(): void
    {
        $this->assertSame(LoadProfile::BLIND_LEVELS, LoadProfile::levels(null, null));
    }

    public function test_the_levels_never_climb_past_the_ceiling(): void
    {
        $this->assertLessThanOrEqual(LoadProfile::ceilingFor(null), max(LoadProfile::levels(64, 400)));
    }

    public function test_the_levels_stay_within_the_run_time_budget(): void
    {
        $this->assertLessThanOrEqual(LoadProfile::MAX_LEVELS, count(LoadProfile::levels(96, 400)));
    }

    public function test_thinning_keeps_the_uncontended_and_most_oversubscribed_ends(): void
    {
        $levels = LoadProfile::levels(96, 400);

        $this->assertSame(1, $levels[0], 'The single-connection point is what the round-trip floor is read from.');
        $this->assertSame(
            LoadProfile::ceilingFor(null),
            end($levels),
            'A pool near the ceiling still needs one level above it, or the curve can never be seen to flatten.'
        );
    }

    /**
     * The case that made this necessary, with the numbers from a real run: a
     * server answering well inside the transport round trip to it, so nearly
     * all of every connection's life is spent in transit and it takes many
     * times as many of them to keep the same pool busy.
     */
    public function test_a_slow_link_inflates_the_concurrency_needed_to_saturate(): void
    {
        $this->assertEqualsWithDelta(53.2, LoadProfile::inflation(0.24, 12.52), 0.1);
    }

    public function test_load_from_the_same_machine_needs_no_inflation(): void
    {
        $this->assertEqualsWithDelta(1.0, LoadProfile::inflation(0.24, 0.0), 0.01);
    }

    public function test_a_route_that_sleeps_is_barely_affected_by_the_network(): void
    {
        // 100ms of sleep dwarfs a 12.52ms wire, which is why the I/O route
        // saturates correctly from a distant generator when the fast ones
        // cannot.
        $this->assertLessThan(1.2, LoadProfile::inflation(101.0, 12.52));
    }

    public function test_the_levels_grow_when_the_network_is_in_the_loop(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, LoadProfile::levels(4, 20));

        $near = $profile->levelsFor(0.24, 0.0);
        $far = $profile->levelsFor(0.24, 12.52);

        $this->assertSame(1, $near[0], 'The uncontended point stays, whatever the link costs.');
        $this->assertSame(1, $far[0]);
        $this->assertGreaterThan(max($near), max($far), 'A distant generator has to offer more connections to reach the same pool.');

        // Spread geometrically rather than by scaling the host's own numbers
        // and clamping: behind a slow enough link every one of those would
        // exceed the ceiling and collapse to a single level, leaving a route
        // with one point where its curve should be.
        $this->assertGreaterThan(4, count(array_unique($far)));
    }

    /**
     * macOS ships a soft limit of 256 descriptors, and one connection is one
     * open file. Asking for 426 on such a machine is what produced the
     * impossible level this suite also guards against.
     */
    /**
     * The bend on the /bench/io curve is the one number on the results page a
     * reader is meant to recognise as their own setting, so a level is spent
     * landing on it rather than bracketing it.
     *
     * The failure this guards: a geometric ladder stepped 15 then 36 against a
     * pool of twenty, the curve flattened at 36, and the page said "which is
     * your worker count" about a number that was not.
     */
    public function test_a_level_lands_where_the_worker_pool_is_predicted_to_bend(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, []);

        // Driven from the same machine, a worker is occupied for as long as the
        // request takes, so the pool bends at the worker count itself.
        $this->assertContains(20, $profile->levelsFor(100.0, 0.0, HttpBenchmarkResults::IO_ROUTE));
    }

    public function test_the_bend_moves_out_with_the_distance_to_the_generator(): void
    {
        $profile = new LoadProfile('https://x', 'external', 100, 4, 20, [], fdLimit: 65536);

        // A connection only occupies a worker while the server holds the
        // request, so reaching the same pool from 12ms away takes more of them.
        $this->assertContains(22, $profile->levelsFor(104.4, 12.03, HttpBenchmarkResults::IO_ROUTE));
    }

    public function test_seeding_the_bend_does_not_grow_the_ladder(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, []);

        $this->assertLessThanOrEqual(LoadProfile::MAX_LEVELS, count($profile->levelsFor(100.0, 0.0)));
        $this->assertSame(1, $profile->levelsFor(100.0, 0.0)[0], 'The uncontended point is never given up for the bend.');
    }

    public function test_the_levels_stay_inside_what_the_generator_can_hold_open(): void
    {
        $profile = new LoadProfile('https://bench.example.com', 'external', 100, 4, 20, [], fdLimit: 256);

        $this->assertLessThanOrEqual(256 - 64, max($profile->levelsFor(0.24, 12.52)));
    }

    public function test_a_generator_that_reports_no_limit_is_assumed_to_have_the_linux_default(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, []);

        $this->assertSame(LoadProfile::ceilingFor(null), $profile->ceiling());
        $this->assertLessThan(LoadProfile::ASSUMED_FD_LIMIT, $profile->ceiling(), 'Headroom is left for the shell and the result file.');
    }

    public function test_the_levels_never_exceed_the_ceiling_however_slow_the_link(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, LoadProfile::levels(4, 20));

        $this->assertLessThanOrEqual($profile->ceiling(), max($profile->levelsFor(0.1, 200.0)));
    }

    /**
     * The ceiling is whatever the generator can physically hold open, not a
     * number BenchKit chose: a large host behind a distant generator is
     * swept as far as that machine's descriptors and ephemeral ports allow.
     */
    public function test_the_ceiling_is_the_lower_of_what_the_generator_measured(): void
    {
        $this->assertSame(28168, LoadProfile::ceilingFor(65536, 28232), 'Ephemeral ports bind before descriptors here.');
        $this->assertSame(960, LoadProfile::ceilingFor(1024, 28232), 'Descriptors bind before ports here.');
        $this->assertSame(65472, LoadProfile::ceilingFor(65536, null), 'A missing port range does not cap anything.');

        $profile = new LoadProfile('https://x', 'external', 100, 192, 384, [], fdLimit: 65536, portRange: 28232);

        $this->assertGreaterThan(512, max($profile->levelsFor(0.3, 13.0, 'static')), 'A large distant host is not stopped at a fixed level.');
        $this->assertLessThanOrEqual($profile->ceiling(), max($profile->levelsFor(0.3, 13.0, 'static')));
    }

    /**
     * The regression that produced "saturating it would take about 539,288
     * connections" on a 64-core host: serversideup/php sizes the FPM pool from
     * memory, so a large box reports thousands of workers, and anchoring the
     * sweep on that number rather than on the cores treats a memory ceiling as
     * a capacity ceiling.
     */
    public function test_a_pool_sized_from_memory_cannot_anchor_a_route_that_needs_a_core(): void
    {
        $profile = new LoadProfile('https://x', 'external', 100, 64, 14497, []);

        $this->assertSame(64, $profile->parallelism('static'), 'A computing route cannot use more workers than there are cores.');
        $this->assertSame(14497, $profile->parallelism(HttpBenchmarkResults::IO_ROUTE), 'A sleeping route is bounded by the pool, not the cores.');
    }

    public function test_the_ladder_for_a_memory_sized_pool_stays_inside_the_ceiling(): void
    {
        $profile = new LoadProfile('https://x', 'external', 100, 64, 14497, []);
        $levels = $profile->levelsFor(0.31, 1.5, 'static');

        $this->assertLessThanOrEqual($profile->ceiling(), max($levels));
        $this->assertLessThanOrEqual(LoadProfile::MAX_LEVELS, count($levels));
        $this->assertSame(1, $levels[0]);
    }

    /**
     * The inflation cap and the level ceiling shared the constant 512 until a
     * run behind a slow link reported an inflation of exactly 512 — a level
     * count standing in for a ratio, with neither figure visibly wrong.
     */
    public function test_inflation_is_capped_by_its_own_limit_not_by_the_level_ceiling(): void
    {
        $this->assertSame(LoadProfile::MAX_INFLATION, LoadProfile::inflation(0.05, 1000.0));
        $this->assertNotSame((float) LoadProfile::ceilingFor(null), LoadProfile::MAX_INFLATION);
    }

    public function test_the_ceiling_is_readable_without_a_profile(): void
    {
        $this->assertSame(192, LoadProfile::ceilingFor(256));
        $this->assertSame(960, LoadProfile::ceilingFor(null));
    }

    public function test_the_peak_is_the_headline_even_when_it_is_not_the_last_level(): void
    {
        // Throughput peaks at 20 and drifts down under more concurrency.
        $curve = $this->curve([1 => 100.0, 10 => 900.0, 20 => 1800.0, 40 => 1790.0, 80 => 1740.0]);

        $this->assertSame(20, $curve->knee());
        $this->assertSame(1800.0, $curve->peakRps());
        $this->assertSame(20, $curve->knee(), 'The peak is the bend, not whichever level past it measured highest.');
    }

    public function test_a_curve_that_flattens_is_saturated(): void
    {
        $curve = $this->curve([1 => 100.0, 10 => 900.0, 20 => 1800.0, 40 => 1810.0, 80 => 1815.0]);

        $this->assertTrue($curve->isSaturated());
    }

    public function test_a_curve_still_climbing_at_the_top_level_is_not_saturated(): void
    {
        // Throughput doubles at every level and never bends.
        $curve = $this->curve([1 => 100.0, 10 => 900.0, 20 => 1800.0, 40 => 3600.0, 80 => 7200.0]);

        $this->assertFalse(
            $curve->isSaturated(),
            'A number the sweep never stopped improving on is a floor, not a maximum, and the results page has to say so.'
        );
    }

    public function test_a_non_2xx_response_is_not_counted_as_a_working_level(): void
    {
        // oha reports a perfect transport success rate for a route answering
        // 503 to everything — and a higher throughput than a working one,
        // because an error is cheap to produce.
        $curve = new LoadCurve('db_read', [
            ['concurrency' => 1, 'result' => $this->measured(100.0, 1)],
            ['concurrency' => 20, 'result' => $this->measured(9000.0, 20, statusCodes: ['503' => 54000])],
        ]);

        $this->assertSame(1, $curve->knee(), 'The headline must fall back to the last level that was actually serving.');
        $this->assertSame(20, $curve->breakingPoint());
    }

    public function test_the_breaking_level_keeps_what_the_route_answered_there(): void
    {
        // "Stopped at 20 connections" is a symptom. The codes are what say
        // whether the database, the front end, or the generator gave out, and
        // the results page cannot say so unless the curve keeps them.
        $curve = new LoadCurve('db_read', [
            ['concurrency' => 1, 'result' => $this->measured(100.0, 1)],
            ['concurrency' => 20, 'result' => $this->measured(9000.0, 20, statusCodes: ['200' => 30000, '503' => 24000])],
            ['concurrency' => 80, 'result' => $this->measured(9000.0, 80, statusCodes: ['503' => 54000])],
        ]);

        $this->assertSame([
            'concurrency' => 20,
            'status_codes' => ['200' => 30000, '503' => 24000],
            'errors' => [],
            'failure' => null,
            'success_rate' => 1.0,
            'total_requests' => 54000,
        ], $curve->breakingResult(), 'The first level that broke is the one to explain, not the worst.');
    }

    public function test_a_level_the_generator_could_not_measure_breaks_with_its_reason(): void
    {
        $curve = new LoadCurve('io', [
            ['concurrency' => 1, 'result' => $this->measured(10.0, 1)],
            ['concurrency' => 4000, 'result' => StepResult::failed('oha exited with status 1')],
        ]);

        $this->assertSame(4000, $curve->breakingResult()['concurrency']);
        $this->assertSame('oha exited with status 1', $curve->breakingResult()['failure']);
    }

    public function test_a_clean_sweep_has_no_breaking_level(): void
    {
        $curve = new LoadCurve('static', [
            ['concurrency' => 1, 'result' => $this->measured(100.0, 1)],
            ['concurrency' => 20, 'result' => $this->measured(1500.0, 20)],
        ]);

        $this->assertNull($curve->breakingResult());
        $this->assertNull($curve->breakingPoint());
    }

    public function test_requests_still_in_flight_at_the_deadline_are_not_failures(): void
    {
        // Every oha run ends with one of these per connection — it is how a
        // timed window stops. Counting them as errors made every level of
        // every route read as broken, which silently skipped the whole
        // response-time pass.
        $result = StepResult::fromOha([
            'summary' => ['requestsPerSec' => 186.6, 'average' => 0.1075, 'fastest' => 0.1, 'successRate' => 1.0, 'total' => 6.0],
            'latencyPercentiles' => ['p50' => 0.1075],
            'statusCodeDistribution' => ['200' => 1100],
            'errorDistribution' => ['aborted due to deadline' => 20],
        ]);

        $this->assertTrue($result->isClean());
    }

    public function test_a_genuine_transport_error_is_still_a_failure(): void
    {
        $result = StepResult::fromOha([
            'summary' => ['requestsPerSec' => 186.6, 'average' => 0.1075, 'fastest' => 0.1, 'successRate' => 1.0, 'total' => 6.0],
            'latencyPercentiles' => ['p50' => 0.1075],
            'statusCodeDistribution' => ['200' => 1100],
            'errorDistribution' => ['aborted due to deadline' => 20, 'connection closed before message completed' => 14],
        ]);

        $this->assertFalse($result->isClean());
    }

    /**
     * The failure this guards, with the numbers from a real run: a generator
     * asked for more connections than its descriptor limit allowed, the ones
     * that could not open were counted as completed requests, and the level
     * reported 17,201 req/s where the one below it managed 398.
     *
     * A connection can only hold one request at a time, so that combination of
     * rate and response time cannot describe 426 connections. Believing it
     * would have made a broken level the headline for the whole route.
     */
    public function test_a_level_that_breaks_the_closed_loop_identity_is_not_a_measurement(): void
    {
        $exhausted = StepResult::fromOha([
            'summary' => ['requestsPerSec' => 17201.0, 'average' => 0.0011, 'fastest' => 0.0001, 'successRate' => 1.0, 'total' => 6.0],
            'latencyPercentiles' => ['p50' => 0.001],
            'statusCodeDistribution' => ['200' => 103206],
        ]);

        $curve = new LoadCurve('static', [
            ['concurrency' => 213, 'result' => $this->measured(398.0, 213)],
            ['concurrency' => 426, 'result' => $exhausted],
        ]);

        $this->assertSame(213, $curve->knee());
        $this->assertSame(398.0, $curve->peakRps());
    }

    public function test_a_route_that_never_answered_correctly_is_dead(): void
    {
        $curve = new LoadCurve('db_read', [
            ['concurrency' => 1, 'result' => $this->measured(900.0, 1, statusCodes: ['503' => 5400])],
        ]);

        $this->assertTrue($curve->isDead());
        $this->assertNull($curve->knee());
    }

    public function test_the_latency_rate_is_a_fraction_of_the_measured_peak(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 4, 20, LoadProfile::levels(4, 20));

        $this->assertSame(1260, $profile->latencyRate(1800.0));
    }

    public function test_the_latency_rate_never_falls_below_one_request_a_second(): void
    {
        $profile = new LoadProfile('http://localhost:8080', 'loopback', 100, 1, 1, LoadProfile::levels(1, 1));

        $this->assertSame(1, $profile->latencyRate(0.4));
    }

    public function test_a_busy_generator_reports_full_connection_efficiency(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->measured(356.0, 50)->connectionEfficiency(50), 0.01);
    }

    public function test_a_generator_leaving_connections_idle_is_visible(): void
    {
        // Same throughput and response time cannot have kept 50 requests in
        // flight — Little's law says that combination needs about 25.
        $starved = StepResult::fromOha([
            'summary' => ['requestsPerSec' => 356.0, 'average' => 0.0702, 'fastest' => 0.01, 'successRate' => 1.0, 'total' => 6.0],
            'latencyPercentiles' => ['p50' => 0.07],
            'statusCodeDistribution' => ['200' => 2136],
        ]);

        $curve = new LoadCurve('static', [['concurrency' => 50, 'result' => $starved]]);

        $this->assertTrue($curve->generatorStarved());
    }

    public function test_the_round_trip_floor_comes_from_the_uncontended_level(): void
    {
        $curve = $this->curve([1 => 100.0, 20 => 1800.0]);

        $this->assertEqualsWithDelta(10.0, $curve->idleLatencyMs(), 0.01);
    }

    public function test_the_io_route_flattens_at_the_worker_count(): void
    {
        // A 100ms sleep holds a worker for its whole duration, so 20 workers
        // cannot serve more than 200 requests a second however many
        // connections are offered. This is the shape the results page draws a
        // predicted ceiling onto, and the reason the levels are centred on the
        // worker count rather than doubling past it.
        $curve = $this->curve([1 => 10.0, 10 => 100.0, 20 => 198.0, 40 => 199.0, 80 => 199.0], route: 'io');

        $this->assertSame(20, $curve->knee());
        $this->assertTrue($curve->isSaturated());
    }
}
