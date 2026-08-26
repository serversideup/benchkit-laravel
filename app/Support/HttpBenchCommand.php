<?php

namespace App\Support;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadStep;

/**
 * The single source of truth for the standard HTTP load. The two batches
 * describe it one step at a time; every renderer — the local self-test, the
 * external generator script, the copy-paste commands in the UI — consumes the
 * same steps, so the load cannot drift between the ways it can be driven.
 *
 * A route is measured in two batches because the second depends on the first.
 * The sweep finds how much the server can take; the response-time pass then
 * offers a rate below that and times the answers. There is no way to know the
 * rate before the sweep has run, which is why this is two batches rather than
 * one list.
 */
class HttpBenchCommand
{
    /** Throwaway load before a route's first measured window; never parsed. */
    public const WARMUP_SECONDS = 3;

    /**
     * Every request is given a deadline so a wedged one becomes a counted
     * error the results can show, rather than a hang that eats the run.
     */
    protected const REQUEST_TIMEOUT = '30s';

    /**
     * Batch one: warm each route, then measure it at one connection.
     *
     * One connection is the only measurement that separates the server from
     * the path to it — nothing is queued, so the time is service plus round
     * trip, and the round trip is already known from the handshake. What is
     * left is how long the server actually takes, which is what decides how
     * many connections the sweep needs.
     *
     * @return array<int, LoadStep>
     */
    public function probe(LoadProfile $profile): array
    {
        $steps = [];
        $index = 0;

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $route) {
            $steps[] = new LoadStep(
                index: ++$index,
                route: $route,
                phase: LoadStep::PHASE_WARMUP,
                connections: 8,
                durationSeconds: $profile->warmupSeconds,
                url: $profile->urlFor($route),
            );

            $steps[] = new LoadStep(
                index: ++$index,
                route: $route,
                phase: LoadStep::PHASE_SWEEP,
                connections: LoadProfile::PROBE_CONCURRENCY,
                durationSeconds: $profile->levelSeconds,
                url: $profile->urlFor($route),
            );
        }

        return $steps;
    }

    /**
     * Batch two: each route at the levels its own probe earned.
     *
     * The probe's own level is skipped — it has already been measured, and
     * measuring it twice would mean two files for one point on the curve.
     *
     * @param  array<string, array<int, int>>  $levels
     * @return array<int, LoadStep>
     */
    public function sweep(LoadProfile $profile, array $levels): array
    {
        $steps = [];
        $index = 0;

        foreach ($levels as $route => $routeLevels) {
            foreach ($routeLevels as $level) {
                if ($level === LoadProfile::PROBE_CONCURRENCY) {
                    continue;
                }

                $steps[] = new LoadStep(
                    index: ++$index,
                    route: $route,
                    phase: LoadStep::PHASE_SWEEP,
                    connections: $level,
                    durationSeconds: $profile->levelSeconds,
                    url: $profile->urlFor($route),
                );
            }
        }

        return $steps;
    }

    /**
     * Batch two: one open-loop pass per route, at a rate the sweep proved the
     * server can hold.
     *
     * A route whose sweep never answered correctly is skipped — there is no
     * honest rate to offer it, and timing a broken route would publish a
     * latency figure for requests that failed.
     *
     * @param  array<string, LoadCurve>  $curves
     * @return array<int, LoadStep>
     */
    public function latency(LoadProfile $profile, array $curves): array
    {
        $steps = [];
        $index = 0;

        foreach ($curves as $route => $curve) {
            if ($curve->isDead() || $curve->knee() === null) {
                continue;
            }

            $steps[] = new LoadStep(
                index: ++$index,
                route: $route,
                phase: LoadStep::PHASE_LATENCY,
                connections: $curve->knee(),
                durationSeconds: $profile->latencySeconds,
                url: $profile->urlFor($route),
                qps: $profile->latencyRate($curve->bestRps()),
            );
        }

        return $steps;
    }

    /**
     * The oha invocation for one step, without redirection.
     *
     * The binary differs by driver and nothing else does: a self-test runs the
     * copy Composer installed, and a generator runs whatever `oha` is on its
     * own PATH. Every flag is shared, which is what makes the two modes the
     * same measurement.
     */
    public function render(LoadStep $step, bool $insecure, ?string $binary = null, ?string $connectTo = null): string
    {
        $flags = sprintf('-z %ds -c %d -t %s --redirect 0', $step->durationSeconds, $step->connections, self::REQUEST_TIMEOUT);

        if ($connectTo !== null) {
            $flags .= ' --connect-to '.escapeshellarg($connectTo);
        }

        if ($step->phase === LoadStep::PHASE_LATENCY) {
            // -q sets the offered rate and --latency-correction is inert
            // without it. -w matters as much as either: without it oha
            // abandons requests still in flight at the deadline, and those are
            // the slowest ones — dropping them biases the tail down, which is
            // exactly the bias the correction exists to remove.
            $flags .= sprintf(' -q %d --latency-correction -w', $step->qps);
        }

        if ($insecure) {
            $flags .= ' --insecure';
        }

        if ($step->isMeasured()) {
            $flags .= ' --no-tui --output-format json';
        }

        return sprintf('%s %s %s', $binary ?? base_path('vendor/bin/oha'), $flags, escapeshellarg($step->url));
    }

    /**
     * Where a step's output belongs, or null for the warmup, which is thrown
     * away rather than written.
     */
    public function outputPath(LoadStep $step): ?string
    {
        $slot = HttpBenchmarkResults::slotFor($step->route, $step->phase, $step->connections);

        return $slot === null ? null : (new HttpBenchmarkResults)->pathForSlot($slot);
    }

    public function isInsecure(LoadProfile $profile): bool
    {
        return str_starts_with($profile->targetUrl, 'https://');
    }
}
