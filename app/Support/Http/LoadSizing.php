<?php

namespace App\Support\Http;

use App\Actions\Results\HttpBenchmarkResults;

/**
 * Turns what the probe measured into the levels the sweep will run.
 *
 * Shared by both drivers rather than written twice. The local self-test does
 * all three phases in one process and the external run does them across three
 * batches of work, but if they sized the sweep differently they would stop
 * being the same measurement — and a difference in a decision, unlike a
 * difference in flags, does not show up in the output.
 */
class LoadSizing
{
    public function __construct(protected HttpBenchmarkResults $results) {}

    /**
     * The levels each route should be swept at, recorded onto the run.
     *
     * @return array<string, array<int, int>>
     */
    public function fromProbe(LoadProfile $profile, ?float $transportRttMs): array
    {
        $levels = [];

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $route) {
            $service = $this->results->probeServiceMs($route, $transportRttMs);
            $levels[$route] = $profile->levelsFor($service, $transportRttMs, $route);
        }

        $this->results->writeLevels($levels);

        return $levels;
    }
}
