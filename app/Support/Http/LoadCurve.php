<?php

namespace App\Support\Http;

/**
 * One route's sweep, read after the fact: where throughput peaked, whether it
 * ever flattened, and whether anything about the measurement makes those
 * numbers untrustworthy.
 *
 * Nothing here drives the load: the levels are chosen before the run starts
 * and this only describes what came back, so it reads a self-test and an
 * external run identically.
 */
class LoadCurve
{
    /**
     * The share of a curve's opening slope that its closing slope has to keep
     * before it counts as still climbing. Below this the server has stopped
     * converting concurrency into throughput, which is the bend.
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
     * lands near 1.0. Between this floor and MIN_EFFICIENCY the level is real
     * but generator-limited — some connections sat idle. Below it the numbers
     * describe replies that never happened, because connections that could not
     * open are counted as completed requests. The upper bound catches the same
     * failure from the other side.
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
        return $this->kneeMeasurement()['concurrency'] ?? null;
    }

    /**
     * The most this route actually served, and the concurrency it happened at.
     *
     * Not the knee, which is the cheapest level within IMPROVEMENT of this one
     * and therefore the right level to hold open while timing responses.
     *
     * @return array{concurrency?: int, result?: StepResult}
     */
    public function peak(): array
    {
        $peak = [];

        foreach ($this->clean() as $measurement) {
            if ($measurement['result']->requestsPerSecond > ($peak['result']->requestsPerSecond ?? 0.0)) {
                $peak = $measurement;
            }
        }

        return $peak;
    }

    public function peakConcurrency(): ?int
    {
        return $this->peak()['concurrency'] ?? null;
    }

    public function peakRps(): float
    {
        return $this->peak()['result']?->requestsPerSecond ?? 0.0;
    }

    public function peakResult(): ?StepResult
    {
        return $this->peak()['result'] ?? null;
    }

    /** The highest concurrency offered, including levels thrown out as implausible. */
    public function topConcurrency(): ?int
    {
        $levels = array_column($this->measurements, 'concurrency');

        return $levels === [] ? null : max($levels);
    }

    /**
     * How many connections were carrying a request when the route peaked.
     *
     * Rate x mean response time. In a closed loop this lands on the concurrency
     * offered when every connection was busy and below it when they were not,
     * which separates a run bounded by the path from one bounded by the machine
     * driving the load. The mean, because that is what the identity is stated in.
     */
    /** What one request cost at the peak, queueing included. */
    public function peakLatencyMs(): ?float
    {
        $result = $this->peakResult();

        return $result?->averageSeconds === null ? null : round($result->averageSeconds * 1000, 2);
    }

    public function busyConnections(): ?float
    {
        $result = $this->peakResult();

        return $result === null || $result->averageSeconds === null
            ? null
            : round($result->averageSeconds * $result->requestsPerSecond, 1);
    }

    /**
     * Whether throughput actually flattened inside the range measured.
     *
     * Asked as a slope rather than as a ratio between the last two levels,
     * because the ladder is geometric and those two can be a factor of four
     * apart. A plain ratio reads "five percent faster" as still climbing even
     * when the five percent cost four times the connections — measured on a
     * 64-thread host, throughput rose 5.3% between 128 and 512 connections
     * while the tail went from 7ms to 49ms, and the run published a flat
     * curve as a floor.
     *
     * Normalised against the curve's own opening slope, so it needs no view of
     * what the host ought to manage: a route that has stopped scaling returns a
     * small fraction of the throughput per connection it returned at the start.
     */
    public function isSaturated(): bool
    {
        $clean = $this->clean();

        if (count($clean) < 2) {
            return false;
        }

        $opening = $this->slopeBetween($clean[0], $clean[1]);

        if ($opening <= 0) {
            return true;
        }

        $top = end($clean);
        $below = prev($clean);

        return $this->slopeBetween($below, $top) <= $opening * self::IMPROVEMENT;
    }

    /**
     * Requests a second gained per connection added between two levels.
     *
     * @param  array{concurrency: int, result: StepResult}  $from
     * @param  array{concurrency: int, result: StepResult}  $to
     */
    protected function slopeBetween(array $from, array $to): float
    {
        $connections = $to['concurrency'] - $from['concurrency'];

        return $connections <= 0
            ? 0.0
            : ($to['result']->requestsPerSecond - $from['result']->requestsPerSecond) / $connections;
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
    public function idleLatencyMs(): ?float
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
     * measurement at all.
     *
     * @see self::MIN_PLAUSIBLE_EFFICIENCY
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
     * The cheapest level within IMPROVEMENT of the peak — the bend.
     *
     * @return array{concurrency?: int, result?: StepResult}
     */
    protected function kneeMeasurement(): array
    {
        $peak = $this->peakRps();

        foreach ($this->clean() as $measurement) {
            if ($measurement['result']->requestsPerSecond >= $peak * (1 - self::IMPROVEMENT)) {
                return $measurement;
            }
        }

        return [];
    }
}
