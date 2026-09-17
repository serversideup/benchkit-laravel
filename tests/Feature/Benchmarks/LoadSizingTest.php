<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadSizing;
use Illuminate\Support\Facades\File;
use Tests\Concerns\SeedsHttpResults;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

/**
 * Turning a probe into a ladder — the step that decides every concurrency the
 * run will measure, and the one that had no assertions of its own while it was
 * producing a six-figure connection count from a 1.81ms network.
 */
class LoadSizingTest extends TestCase
{
    use SeedsHttpResults;
    use UsesFakeResultsPath;

    protected function profile(?int $cores = 4, ?int $workers = 20): LoadProfile
    {
        return new LoadProfile('https://bench.example.com', 'app-url', 100, $cores, $workers, LoadProfile::levels($cores, $workers));
    }

    /** One probe window, at whatever response time it measured. */
    protected function seedProbe(string $route, float $p50Ms): void
    {
        $results = new HttpBenchmarkResults;
        $path = $results->sweepPath($route, LoadProfile::PROBE_CONCURRENCY);

        $this->writeOha($path, 1000.0, 1, null, $p50Ms / 1000);
    }

    public function test_the_probe_takes_only_the_transport_round_trip_off_the_measured_response(): void
    {
        $this->seedProbe('static', 2.11);

        $this->assertEqualsWithDelta(0.61, (new HttpBenchmarkResults)->probeServiceMs('static', 1.5), 0.01);
    }

    /**
     * The regression, named. The handshake's rtt_ms is the floor of five full
     * HTTP requests, so it already contains the server's work; subtracting it
     * from a response time that contains the same work leaves nothing, and
     * "nothing" used to become the 0.05ms floor — the denominator of the
     * largest multiplier in the sweep.
     */
    public function test_a_round_trip_that_already_contains_the_server_resolves_nothing(): void
    {
        $this->seedProbe('static', 1.86);

        $results = new HttpBenchmarkResults;

        $this->assertNull($results->probeServiceMs('static', 1.81), 'A difference of two nearly equal measurements is unresolved, not small.');
        $this->assertEqualsWithDelta(0.36, $results->probeServiceMs('static', 1.5), 0.01);
    }

    public function test_a_service_time_below_the_noise_floor_is_unknown_rather_than_the_floor_itself(): void
    {
        $this->seedProbe('static', 1.52);

        $this->assertNull((new HttpBenchmarkResults)->probeServiceMs('static', 1.5));
    }

    public function test_a_missing_transport_round_trip_leaves_the_response_alone(): void
    {
        $this->seedProbe('static', 2.4);

        $this->assertEqualsWithDelta(2.4, (new HttpBenchmarkResults)->probeServiceMs('static', null), 0.01);
    }

    public function test_a_route_that_was_never_probed_reports_nothing(): void
    {
        $this->assertNull((new HttpBenchmarkResults)->probeServiceMs('static', 1.5));
    }

    public function test_the_sizing_records_one_ladder_per_route_and_no_prediction(): void
    {
        $results = new HttpBenchmarkResults;
        $results->writeMeta(['url' => 'https://bench.example.com', 'mode' => 'app-url'], $this->profile(), 20);

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $route) {
            $this->seedProbe($route, 2.4);
        }

        $levels = (new LoadSizing($results))->fromProbe($this->profile(), 1.5);

        $this->assertSame(array_keys(HttpBenchmarkResults::ROUTES), array_keys($levels));

        $meta = json_decode(File::get($results->metaPath()), true);
        $this->assertArrayNotHasKey('required_concurrency', $meta, 'A closed loop cannot be extrapolated past what it measured.');
        $this->assertSame($levels, $meta['levels']);
    }

    public function test_the_sleeping_route_gets_a_different_ladder_from_the_fast_ones(): void
    {
        $results = new HttpBenchmarkResults;
        $results->writeMeta(['url' => 'https://bench.example.com', 'mode' => 'app-url'], $this->profile(), 20);

        $this->seedProbe('static', 0.6);
        $this->seedProbe(HttpBenchmarkResults::IO_ROUTE, 101.5);

        $levels = (new LoadSizing($results))->fromProbe($this->profile(), 1.5);

        $this->assertNotSame($levels['static'], $levels[HttpBenchmarkResults::IO_ROUTE]);
        $this->assertContains(20, $levels[HttpBenchmarkResults::IO_ROUTE], 'The sleeping route bends at the pool.');
    }

    public function test_an_unresolvable_service_time_falls_back_to_the_host_ladder(): void
    {
        $results = new HttpBenchmarkResults;
        $results->writeMeta(['url' => 'https://bench.example.com', 'mode' => 'app-url'], $this->profile(), 20);

        $this->seedProbe('static', 1.52);

        $levels = (new LoadSizing($results))->fromProbe($this->profile(), 1.5);

        $this->assertSame(LoadProfile::levels(4, 20), $levels['static']);
    }

    /**
     * A large host reports an FPM pool sized from memory. Anchored on that, the
     * ladder asks for concurrency no number of cores could occupy.
     */
    public function test_a_memory_sized_pool_does_not_push_the_ladder_past_the_cores(): void
    {
        $results = new HttpBenchmarkResults;
        $big = new LoadProfile('https://bench.example.com', 'app-url', 100, 64, 14497, LoadProfile::levels(64, 14497));
        $results->writeMeta(['url' => 'https://bench.example.com', 'mode' => 'app-url'], $big, 14497);

        $this->seedProbe('static', 1.81);

        $levels = (new LoadSizing($results))->fromProbe($big, 0.3);

        $this->assertLessThanOrEqual(LoadProfile::MAX_CONCURRENCY, max($levels['static']));
        $this->assertLessThanOrEqual(LoadProfile::MAX_LEVELS, count($levels['static']));
    }
}
