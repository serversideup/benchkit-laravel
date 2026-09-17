<?php

namespace Tests\Unit\Http;

use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\StepResult;
use Tests\TestCase;

/**
 * A real run against a 32-core EPYC 9354P, with the generator 0.43ms away in
 * the same datacenter. Every curve here is the one that machine produced.
 *
 * It is pinned because reasoning about saturation from a single host is how
 * each of these went unnoticed: the numbers looked plausible in isolation and
 * only the shape of the whole curve showed what they meant.
 */
class EpycRunTest extends TestCase
{
    /**
     * @param  array<int, array{0: int, 1: float, 2: float}>  $points  concurrency, rps, p50 ms
     */
    protected function curve(string $route, array $points): LoadCurve
    {
        return new LoadCurve($route, array_map(fn (array $p): array => [
            'concurrency' => $p[0],
            'result' => StepResult::fromOha([
                'summary' => [
                    'successRate' => 1.0,
                    'requestsPerSec' => $p[1],
                    'average' => $p[0] / $p[1],
                    'total' => 6.0,
                    'totalData' => 100000,
                ],
                'latencyPercentiles' => ['p50' => $p[2] / 1000, 'p95' => $p[2] / 1000],
                'statusCodeDistribution' => ['200' => (int) round($p[1] * 6)],
                'errorDistribution' => ['aborted due to deadline' => $p[0]],
            ]),
        ], $points));
    }

    /**
     * Throughput rose 5.3% between 128 and 512 connections while the tail went
     * from 7ms to 49ms. Comparing only the last two levels called that still
     * climbing, and the run published a plateau as a floor.
     */
    public function test_a_curve_that_gained_five_percent_for_four_times_the_connections_is_saturated(): void
    {
        $static = $this->curve('static', [
            [1, 1141.9, 0.86], [3, 3333.2, 0.89], [12, 12466.6, 0.93],
            [42, 31173.5, 1.25], [128, 43089.2, 2.29], [512, 45372.4, 5.14],
        ]);

        $this->assertTrue($static->isSaturated(), 'The curve flattened; the peak is a maximum, not a floor.');
        $this->assertSame(45372.4, $static->peakRps());
    }

    public function test_the_json_route_from_the_same_run_is_also_saturated(): void
    {
        $this->assertTrue($this->curve('json', [
            [1, 1024.8, 0.95], [3, 3174.7, 0.93], [12, 11907.3, 0.98],
            [40, 29996.9, 1.26], [117, 40969.4, 2.33], [468, 41721.8, 4.59],
        ])->isSaturated());
    }

    /**
     * The guard against calling everything saturated: this route was still
     * converting connections into throughput when it was cut off, and doubling
     * the concurrency still doubled the rate.
     */
    public function test_a_route_still_scaling_linearly_is_not_saturated(): void
    {
        $this->assertFalse($this->curve('io', [
            [1, 9.8, 101.88], [2, 19.7, 101.85], [4, 39.3, 101.9],
            [8, 78.6, 101.9], [16, 157.3, 101.97], [32, 314.5, 102.24],
        ])->isSaturated(), 'Every doubling doubled the throughput; nothing about that is flat.');
    }

    public function test_the_db_route_that_broke_before_flattening_is_not_saturated(): void
    {
        $this->assertFalse($this->curve('db_read', [
            [1, 776.3, 1.26], [3, 2443.2, 1.19], [11, 8555.2, 1.24], [36, 21501.4, 1.59],
        ])->isSaturated());
    }

    /**
     * FrankenPHP in worker mode declares no thread count, so this run recorded
     * workers as null. The sleeping route then sized itself from the blind
     * default and swept a 64-core host to 32 connections, reporting the end of
     * its own ladder as the machine's ceiling.
     */
    public function test_the_sleeping_route_is_sized_from_the_cores_when_no_worker_count_is_declared(): void
    {
        $profile = new LoadProfile('https://x', 'app-url', 100, 64, null, []);

        $this->assertSame(64, $profile->parallelism('io'));
        $this->assertGreaterThan(200, max($profile->levelsFor(101.45, 0.43, 'io')));
    }
}
