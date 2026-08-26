<?php

namespace App\Support\Http;

/**
 * One route's sweep, read after the fact: where throughput peaked, whether it
 * ever flattened, and whether anything about the measurement makes those
 * numbers untrustworthy.
 *
 * Nothing here drives the load. The levels are chosen before the run starts
 * (LoadProfile::levelsFor) and run as a plain list, so this only has to describe
 * what came back — which is why the same class can read a self-test and an
 * external run without either of them knowing it exists.
 */
class LoadCurve
{
    /**
     * How much the top level has to beat the one below it before the curve is
     * treated as still climbing rather than flattened. Below this the server
     * stopped converting concurrency into throughput, which is the bend.
     */
    protected const IMPROVEMENT = 0.05;

    /**
     * A generator that kept every connection busy sits at 1.0. Below this,
     * connections were idle and the numbers describe the generator.
     */
    protected const MIN_EFFICIENCY = 0.90;

    /**
     * The band inside which the closed-loop identity still holds well enough
     * for a level to be a measurement of anything.
     *
     * A connection can only hold one request at a time, so concurrency = rate
     * x response time is arithmetic rather than a model, and a real level
     * lands within a few percent of 1.0. Between this floor and MIN_EFFICIENCY
     * the level is real but generator-limited — some connections sat idle.
     * Below it the numbers describe replies that never happened: connections
     * that could not open are counted as completed requests, which is how a
     * static route came back with 17,201 req/s at a level where the one below
     * it managed 398. That reads as an efficiency of 0.04, not of 40.
     */
    protected const MIN_PLAUSIBLE_EFFICIENCY = 0.50;

    protected const MAX_PLAUSIBLE_EFFICIENCY = 1.30;

    /**
     * @param  array<int, array{concurrency: int, result: StepResult}>  $measurements
     */
    public function __construct(
        public readonly string $route,
        protected array $measurements,
    ) {}

    /**
     * The lowest concurrency that reaches the route's best throughput.
     *
     * Deliberately not the level that measured the single highest number.
     * Along a plateau the levels are within noise of each other, so "highest"
     * is decided by whichever run got lucky — and on /bench/io that reports a
     * bend at forty when the pool holds twenty, which is the one number on the
     * page a reader is meant to recognise as their own worker count.
     *
     * The knee is where you get the maximum for the least concurrency.
     * Everything past it buys queue rather than throughput, which is also why
     * it is the right level for the latency pass to reuse.
     */
    public function knee(): ?int
    {
        return $this->best()['concurrency'] ?? null;
    }

    /**
     * The most this route actually served, and the concurrency it happened at.
     *
     * Deliberately separate from the knee. The knee is the cheapest way to get
     * within a few percent of this, which makes it the right level to hold
     * open while timing responses — but it is not the maximum, and reporting
     * its throughput under a heading that says "max" was simply wrong: a route
     * peaking at 437 req/s was published as 417.
     *
     * @return array{concurrency?: int, result?: StepResult}
     */
    public function peak(): array
    {
        $best = [];
        $bestRps = 0.0;

        foreach ($this->clean() as $measurement) {
            if ($measurement['result']->requestsPerSecond > $bestRps) {
                $bestRps = $measurement['result']->requestsPerSecond;
                $best = $measurement;
            }
        }

        return $best;
    }

    public function peakConcurrency(): ?int
    {
        return $this->peak()['concurrency'] ?? null;
    }

    public function bestRps(): float
    {
        return $this->peak()['result']?->requestsPerSecond ?? 0.0;
    }

    public function bestResult(): ?StepResult
    {
        return $this->peak()['result'] ?? null;
    }

    /**
     * Whether throughput actually flattened inside the range measured.
     *
     * If the highest level was still the fastest by a clear margin, the server
     * had more to give and was never found — so the figure is the most
     * BenchKit managed to ask for, not the most the machine can serve. The
     * results page has to say so rather than print it flat.
     */
    public function isSaturated(): bool
    {
        $clean = $this->clean();

        if (count($clean) < 2) {
            return false;
        }

        $top = end($clean);
        $below = prev($clean);

        return $top['result']->requestsPerSecond <= $below['result']->requestsPerSecond * (1 + self::IMPROVEMENT);
    }

    /**
     * The route never answered correctly at any level, so there is nothing to
     * confirm and no rate to time.
     */
    public function isDead(): bool
    {
        return $this->clean() === [];
    }

    /**
     * The lowest concurrency at which the route stopped answering correctly.
     *
     * Worth publishing on its own: it is the only number in a run that says
     * where a server starts failing rather than where it starts slowing.
     */
    public function breakingPoint(): ?int
    {
        foreach ($this->measurements as $measurement) {
            if (! $measurement['result']->isClean()) {
                return $measurement['concurrency'];
            }
        }

        return null;
    }

    /**
     * The uncontended round trip, measured at one connection — a far better
     * floor than the fastest request inside a saturated run, where even the
     * quickest observation has queued behind something.
     */
    public function rttFloorMs(): ?float
    {
        foreach ($this->measurements as $measurement) {
            if ($measurement['concurrency'] === 1) {
                return $measurement['result']->p50Ms;
            }
        }

        return null;
    }

    /**
     * Whether any level shows the generator failing to keep its connections
     * busy, which would mean the numbers describe the machine driving the load
     * rather than the one serving it. See StepResult::connectionEfficiency().
     *
     * Reads every level rather than only the plausible ones: a level thrown
     * out for being implausible is the strongest evidence there is that the
     * generator ran out of room, and skipping it would hide the reason the
     * curve has a gap.
     */
    public function generatorStarved(): bool
    {
        foreach ($this->measurements as $measurement) {
            $efficiency = $measurement['result']->connectionEfficiency($measurement['concurrency']);

            if ($efficiency !== null && $efficiency < self::MIN_EFFICIENCY) {
                return true;
            }
        }

        return false;
    }

    /**
     * The published curve: one point per level measured.
     *
     * @return array<int, array<string, mixed>>
     */
    public function points(): array
    {
        return array_map(fn (array $measurement): array => [
            'concurrency' => $measurement['concurrency'],
            'requests_per_second' => $measurement['result']->requestsPerSecond,
            'p50_ms' => $measurement['result']->p50Ms,
            'p95_ms' => $measurement['result']->p95Ms,
            'success_rate' => $measurement['result']->successRate,
            'connection_efficiency' => $measurement['result']->connectionEfficiency($measurement['concurrency']),
        ], $this->measurements);
    }

    /**
     * @return array<int, array{concurrency: int, result: StepResult}>
     */
    protected function clean(): array
    {
        return array_values(array_filter(
            $this->measurements,
            fn (array $measurement): bool => $measurement['result']->isClean() && $this->isPlausible($measurement)
        ));
    }

    /**
     * Whether a level obeys the closed-loop identity well enough to be a
     * measurement at all. @see self::MAX_EFFICIENCY
     *
     * @param  array{concurrency: int, result: StepResult}  $measurement
     */
    protected function isPlausible(array $measurement): bool
    {
        $efficiency = $measurement['result']->connectionEfficiency($measurement['concurrency']);

        return $efficiency === null
            || ($efficiency >= self::MIN_PLAUSIBLE_EFFICIENCY && $efficiency <= self::MAX_PLAUSIBLE_EFFICIENCY);
    }

    /**
     * @return array{concurrency?: int, result?: StepResult}
     */
    protected function best(): array
    {
        $clean = $this->clean();
        $peak = 0.0;

        foreach ($clean as $measurement) {
            $peak = max($peak, $measurement['result']->requestsPerSecond);
        }

        foreach ($clean as $measurement) {
            if ($measurement['result']->requestsPerSecond >= $peak * (1 - self::IMPROVEMENT)) {
                return $measurement;
            }
        }

        return [];
    }
}
