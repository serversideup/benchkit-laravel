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
     * @return array{levels: array<string, array<int, int>>, required: ?int}
     */
    public function fromProbe(LoadProfile $profile, ?float $rttMs): array
    {
        $levels = [];
        $required = null;

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $route) {
            $service = $this->results->probeServiceMs($route, $rttMs);
            $levels[$route] = $profile->levelsFor($service, $rttMs);

            // Reported from the fastest route, because that is the one the
            // network swamps first and therefore the one that decides whether
            // this generator can find the server's ceiling at all.
            $needed = $profile->requiredConcurrency($service, $rttMs);

            if ($needed !== null) {
                $required = max($required ?? 0, $needed);
            }
        }

        $this->results->writeLevels($levels, $required);

        return ['levels' => $levels, 'required' => $required];
    }
}
