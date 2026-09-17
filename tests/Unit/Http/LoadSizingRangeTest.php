<?php

namespace Tests\Unit\Http;

use App\Support\Http\LoadProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The sweep has to find the plateau on a one-core VPS and on a 128-core box,
 * over loopback and over a network, without anyone tuning it.
 *
 * Throughput flattens near parallelism x inflation, so the ladder has to carry
 * measured points past that or the run reports a floor and calls it a maximum.
 * These assert the margin across the range rather than on whichever host was in
 * front of us, because every previous sizing bug was found one host at a time.
 */
class LoadSizingRangeTest extends TestCase
{
    /** Cores and workers as real hosts report them. */
    protected const HOSTS = [
        '1-core VPS' => [1, 20],
        '2-core VPS' => [2, 20],
        '4-core VPS' => [4, 20],
        '8-core' => [8, 40],
        '16-core' => [16, 80],
        '32-core' => [32, 64],
        '64-thread EPYC' => [64, 64],
        '64-core, memory-sized pool' => [64, 14497],
    ];

    /** Enough points past the bend to see a plateau rather than infer one. */
    protected const MIN_MARGIN = 2.0;

    protected function profile(int $cores, int $workers): LoadProfile
    {
        return new LoadProfile('https://x', 'app-url', 100, $cores, $workers, [], fdLimit: 65536);
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function paths(): array
    {
        return [
            'self-test over loopback' => [0.43, 0.0],
            'generator in the same datacenter' => [0.43, 0.43],
        ];
    }

    #[DataProvider('paths')]
    public function test_the_sweep_pushes_past_saturation_on_every_size_of_host(float $serviceMs, float $rttMs): void
    {
        $inflation = LoadProfile::inflation($serviceMs, $rttMs);

        foreach (self::HOSTS as $label => [$cores, $workers]) {
            $profile = $this->profile($cores, $workers);
            $saturation = $profile->parallelism('static') * $inflation;
            $top = max($profile->levelsFor($serviceMs, $rttMs, 'static'));

            $this->assertGreaterThanOrEqual(
                self::MIN_MARGIN,
                $top / $saturation,
                "{$label}: the sweep tops out at {$top} but saturation is near ".round($saturation).
                ', so the curve cannot be shown to flatten and the run reports a floor.'
            );
        }
    }

    #[DataProvider('paths')]
    public function test_every_host_gets_a_usable_curve_rather_than_a_collapsed_ladder(float $serviceMs, float $rttMs): void
    {
        foreach (self::HOSTS as $label => [$cores, $workers]) {
            $levels = $this->profile($cores, $workers)->levelsFor($serviceMs, $rttMs, 'static');

            $this->assertSame(LoadProfile::PROBE_CONCURRENCY, $levels[0], "{$label}: lost the uncontended point.");
            $this->assertGreaterThanOrEqual(4, count($levels), "{$label}: too few points to read a curve from.");
            $this->assertLessThanOrEqual(LoadProfile::MAX_LEVELS, count($levels), "{$label}: over the run-time budget.");
            $this->assertSame($levels, array_unique($levels), "{$label}: duplicate levels collapsed the ladder.");
        }
    }

    /**
     * A distant generator cannot reach saturation on a large host at any
     * concurrency BenchKit will offer. That is a real limit rather than a
     * sizing bug, and the run says so instead of publishing a maximum.
     */
    public function test_a_distant_generator_is_reported_rather_than_sized_around(): void
    {
        $profile = $this->profile(64, 64);
        $top = max($profile->levelsFor(0.43, 30.0, 'static'));
        $saturation = $profile->parallelism('static') * LoadProfile::inflation(0.43, 30.0);

        $this->assertLessThan($saturation, $top, 'A cross-region sweep cannot reach saturation on a large host.');
        $this->assertSame(LoadProfile::MAX_CONCURRENCY, $top, 'It should still offer everything it has.');
    }
}
