<?php

namespace App\Support\Http;

use App\Actions\Results\HttpBenchmarkResults;

/**
 * The load a run will offer, decided before any of it runs.
 *
 * Every renderer builds from one of these — the local self-test chain and the
 * external generator's script — so the load cannot drift between the ways it
 * can be driven. That is the invariant HttpBenchCommand has always held; the
 * only thing that changes here is that a route is measured at several
 * concurrency levels instead of one.
 */
class LoadProfile
{
    /**
     * Levels used when the worker count could not be probed.
     *
     * Spread wide rather than fine: without a worker count there is nothing to
     * centre on, so the job is to bracket wherever the plateau turns out to be.
     */
    public const BLIND_LEVELS = [1, 8, 32, 128, 256];

    /** Nothing is measured above this, whatever the host suggests. */
    public const MAX_CONCURRENCY = 512;

    /**
     * Descriptors left for everything that is not a load connection: the
     * shell, curl, the result file, and whatever the operating system holds
     * open on the process's behalf.
     */
    protected const DESCRIPTOR_HEADROOM = 64;

    /** Levels per route. Each one costs levelSeconds of every run. */
    public const MAX_LEVELS = 6;

    /**
     * The probe: one connection, nothing queued.
     *
     * Every route starts here because it is the only measurement that
     * separates the server from the path to it. Everything after it is sized
     * from what it finds.
     */
    public const PROBE_CONCURRENCY = 1;

    /** Below this a measured service time is noise, not a number. */
    protected const MIN_SERVICE_MS = 0.05;

    /**
     * @param  array<int, int>  $levels
     */
    public function __construct(
        public readonly string $targetUrl,
        public readonly string $targetMode,
        public readonly int $ioMs,
        public readonly ?int $cores,
        public readonly ?int $workers,
        public readonly array $levels,
        public readonly int $warmupSeconds = 3,
        public readonly int $levelSeconds = 6,
        public readonly int $latencySeconds = 10,
        public readonly float $latencyLoad = 0.70,
        /**
         * How many connections the machine driving the load can hold open.
         * Null when it did not say, which is every self-test — the ceiling
         * then comes from MAX_CONCURRENCY alone.
         */
        public readonly ?int $fdLimit = null,
        /**
         * The address the target's name resolved to from wherever the load is
         * driven. Null for a self-test, and for any run whose generator did
         * not report one.
         */
        public readonly ?string $targetIp = null,
    ) {}

    /**
     * oha's `--connect-to` argument, or null when there is nothing to pin.
     *
     * Sends every window to an address settled once, at the handshake, while
     * leaving the URL alone so the Host header and the TLS name it presents
     * stay exactly what a real client would send. Without it each window looks
     * the name up again, and a resolver worn down by the run itself fails the
     * rest of it with an error about DNS in the middle of a test that has
     * nothing to do with DNS.
     */
    public function connectTo(): ?string
    {
        if ($this->targetIp === null) {
            return null;
        }

        $parts = parse_url($this->targetUrl);
        $host = $parts['host'] ?? null;

        if ($host === null) {
            return null;
        }

        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

        return sprintf('%s:%d:%s:%d', $host, $port, $this->targetIp, $port);
    }

    /**
     * The most concurrency this run may ask for.
     *
     * A connection is an open file, so a generator's descriptor limit is a
     * hard bound on what it can offer — and one it does not enforce politely.
     * Asking for more than it can hold does not produce a slow result, it
     * produces a fast wrong one: the connections that fail to open are counted
     * as completed requests, so throughput appears to jump by an order of
     * magnitude at exactly the level where the measurement stopped being real.
     */
    public function ceiling(): int
    {
        if ($this->fdLimit === null) {
            return self::MAX_CONCURRENCY;
        }

        return max(2, min(self::MAX_CONCURRENCY, $this->fdLimit - self::DESCRIPTOR_HEADROOM));
    }

    /**
     * @param  array{url: string, mode: string}  $target
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $target, array $settings, ?int $cores, ?int $workers, ?int $fdLimit = null): self
    {
        $config = config('benchmark.http.sweep', []);

        return new self(
            targetUrl: $target['url'],
            targetMode: $target['mode'],
            ioMs: (int) ($settings['http_io_ms'] ?? config('benchmark.http.io_ms')),
            cores: $cores,
            workers: $workers,
            levels: self::levels($cores, $workers),
            warmupSeconds: (int) ($config['warmup_seconds'] ?? 3),
            levelSeconds: (int) ($config['level_seconds'] ?? 6),
            latencySeconds: (int) ($config['latency_seconds'] ?? 10),
            latencyLoad: (float) ($config['latency_load'] ?? 0.70),
            fdLimit: $fdLimit,
        );
    }

    /**
     * How much of a request is spent somewhere other than the server.
     *
     * A connection can only hold one request at a time, so to keep W workers
     * busy you need W x (total / service) connections open. When the load
     * comes from the same machine that ratio is about 1 and the worker count
     * is the concurrency that matters. When it comes over a network it is not:
     * measured here, a server answering in 0.24ms behind a 12.52ms round trip
     * needs fifty-two times the connections to reach the same pool, because
     * every connection spends 98% of its life in transit.
     *
     * Sizing the sweep from cores and workers alone was right for a self-test
     * and wrong for the default mode. This is the correction factor.
     */
    public static function inflation(?float $serviceMs, ?float $rttMs): float
    {
        $service = max(self::MIN_SERVICE_MS, (float) $serviceMs);
        $rtt = max(0.0, (float) $rttMs);

        return $service <= 0 ? 1.0 : min(self::MAX_CONCURRENCY, ($service + $rtt) / $service);
    }

    /**
     * The concurrency it would take to saturate this host from where the load
     * is coming from. Reported when it is beyond what BenchKit will offer, so
     * a run that cannot reach a maximum can say how far short it fell.
     */
    public function requiredConcurrency(?float $serviceMs, ?float $rttMs): ?int
    {
        if ($this->workers === null || $serviceMs === null) {
            return null;
        }

        return (int) round($this->workers * self::inflation($serviceMs, $rttMs));
    }

    /**
     * The levels a route is swept at, sized from what its probe measured.
     *
     * Per route rather than per run, because service time is what sets them
     * and it differs by two orders of magnitude across the four: the I/O route
     * sleeps 100ms and needs a few dozen connections, while a static response
     * behind the same network needs hundreds. One shared ladder would either
     * miss the fast routes' bend or spend the whole run queueing on the slow
     * one.
     *
     * @return array<int, int>
     */
    public function levelsFor(?float $serviceMs, ?float $rttMs): array
    {
        if ($serviceMs === null) {
            return self::levels($this->cores, $this->workers);
        }

        $inflation = self::inflation($serviceMs, $rttMs);

        // The most this run will offer: enough to push well past the pool, so
        // the curve has room to flatten rather than ending while it still
        // rises. Twice the worker count was not enough — the I/O route topped
        // out at 45 against a pool of 20 and read as "still climbing" when it
        // was two levels short of showing its plateau.
        $top = min($this->ceiling(), max(2, (int) round(($this->workers ?? 8) * 4 * $inflation)));

        // Spaced geometrically between one connection and that top, rather
        // than by scaling each of the host's own numbers and clamping.
        //
        // Clamping was wrong in exactly the case that matters most. Behind a
        // 13.5ms round trip a server answering in 0.09ms needs about 150x the
        // connections, so cores, cores x 2, workers and workers x 2 all
        // multiplied past the ceiling and every one of them clamped to it —
        // five levels collapsing into one, and a route with a single point
        // where its curve should be. A geometric ladder keeps its spread
        // whatever the inflation turns out to be.
        $candidates = [self::PROBE_CONCURRENCY];
        $steps = self::MAX_LEVELS - 1;

        for ($step = 1; $step <= $steps; $step++) {
            $candidates[] = max(2, (int) round($top ** ($step / $steps)));
        }

        // Put one measured point where the worker pool is predicted to bend.
        //
        // A geometric ladder lands wherever the arithmetic puts it, and on a
        // twenty-worker pool it stepped 15 then 36 — so the /bench/io curve
        // flattened at 36 and the results page said "which is your worker
        // count" about a number that was not. The bend is the one figure on
        // that page a reader is meant to recognise as their own setting, so it
        // is worth spending a level on rather than bracketing.
        //
        // Not at `workers` exactly: a connection only occupies a worker while
        // the server has the request, so keeping the pool busy from a distance
        // takes that many connections times the inflation above.
        $candidates = self::seedPoolBend($candidates, $inflation);

        $levels = array_values(array_unique($candidates));
        sort($levels);

        return $levels;
    }

    /**
     * Swap the level nearest the predicted bend for the bend itself, keeping
     * the ladder the same length.
     *
     * @param  array<int, int>  $candidates
     * @return array<int, int>
     */
    protected function seedPoolBend(array $candidates, float $inflation): array
    {
        if ($this->workers === null || $this->workers < 1) {
            return $candidates;
        }

        $bend = min($this->ceiling(), max(2, (int) round($this->workers * $inflation)));
        $nearest = null;

        foreach ($candidates as $index => $level) {
            // The single-connection point is never given up: it is the only
            // one that measures the server with nothing queued.
            if ($level === self::PROBE_CONCURRENCY) {
                continue;
            }

            if ($nearest === null || abs($level - $bend) < abs($candidates[$nearest] - $bend)) {
                $nearest = $index;
            }
        }

        if ($nearest !== null) {
            $candidates[$nearest] = $bend;
        }

        return $candidates;
    }

    /**
     * The concurrency levels this host is measured at.
     *
     * A machine has two separate ceilings and the levels have to bracket both,
     * because different routes hit different ones.
     *
     * The CPU ceiling sits near the core count: /bench/static and /bench/json
     * compute and return, so throughput climbs until the cores are busy and
     * then flattens however many more requests are offered.
     *
     * The pool ceiling sits at the worker count: /bench/io holds a worker for
     * the length of its simulated wait, so it is bounded by how many workers
     * exist rather than by how fast they are.
     *
     * Seeding from only one of the two was a real bug. With a pool sized from
     * memory — say eighty-five workers on four cores — worker-only levels run
     * 1, 43, 85, 170, and the CPU routes bend at about six. The measurement
     * jumps straight over the bend and reports it at forty-three.
     *
     * Deriving all of this from the machine, rather than hardcoding a number,
     * is what makes one setting fit a one-core box and a thirty-two-core box.
     * A fixed fifty connections is twelve times oversubscribed on the first
     * and barely warm on the second, and those are not the same test.
     *
     * @return array<int, int>
     */
    public static function levels(?int $cores, ?int $workers): array
    {
        $candidates = [1];

        if ($cores !== null && $cores >= 1) {
            array_push($candidates, $cores, $cores * 2);
        }

        if ($workers !== null && $workers >= 1) {
            array_push($candidates, $workers, $workers * 2, $workers * 4);
        }

        if (count($candidates) === 1) {
            return self::BLIND_LEVELS;
        }

        // Clamped rather than discarded. Dropping everything above the ceiling
        // would leave the top level sitting exactly on the pool size, so the
        // sweep could never measure past it and could never show the curve
        // flatten — a plateau needs a point on the far side of the bend.
        $levels = array_map(fn (int $level): int => min($level, self::MAX_CONCURRENCY), $candidates);
        $levels = array_values(array_unique($levels));

        sort($levels);

        // Keep the ends: the uncontended point and the most oversubscribed one
        // are the two the results page reads directly. Thin from the middle.
        while (count($levels) > self::MAX_LEVELS) {
            array_splice($levels, (int) floor(count($levels) / 2), 1);
        }

        return $levels;
    }

    /**
     * Rebuild the profile from the meta file the stage wrote.
     *
     * The levels are read back rather than recomputed: they were derived from
     * this host's cores and workers at the moment the stage started, and a
     * driver that recomputed them could disagree with the file the results are
     * being written into.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function fromMeta(array $meta): self
    {
        return new self(
            targetUrl: $meta['target'],
            targetMode: $meta['mode'],
            ioMs: (int) $meta['io_ms'],
            cores: isset($meta['cores']) ? (int) $meta['cores'] : null,
            workers: isset($meta['workers']) ? (int) $meta['workers'] : null,
            // The fallback ladder only. Per-route levels live in the meta file
            // and are read from there, because the probe sizes them
            // independently for each route.
            levels: self::levels(
                isset($meta['cores']) ? (int) $meta['cores'] : null,
                isset($meta['workers']) ? (int) $meta['workers'] : null,
            ),
            levelSeconds: (int) ($meta['level_seconds'] ?? 6),
            latencySeconds: (int) ($meta['latency_seconds'] ?? 10),
            latencyLoad: (float) ($meta['latency_load'] ?? 0.70),
            fdLimit: isset($meta['generator']['fd_limit']) ? (int) $meta['generator']['fd_limit'] : null,
            targetIp: $meta['generator']['target_ip'] ?? null,
        );
    }

    /**
     * The same load, pointed at a different origin.
     *
     * External mode measures the machine through its public URL rather than
     * the loopback a self-test uses, but everything else about the load — the
     * levels, the durations, the offered fraction — has to stay identical, or
     * the two modes stop being the same test.
     *
     * @param  array{url: string, mode: string}  $target
     */
    public function against(array $target): self
    {
        return new self(
            targetUrl: $target['url'],
            targetMode: $target['mode'],
            ioMs: $this->ioMs,
            cores: $this->cores,
            workers: $this->workers,
            levels: $this->levels,
            warmupSeconds: $this->warmupSeconds,
            levelSeconds: $this->levelSeconds,
            latencySeconds: $this->latencySeconds,
            latencyLoad: $this->latencyLoad,
            fdLimit: $this->fdLimit,
            targetIp: $this->targetIp,
        );
    }

    /**
     * The URL for a route, carrying the simulated delay only where it applies.
     */
    public function urlFor(string $route): string
    {
        // Trailing slash trimmed: APP_URL commonly carries one, and the route
        // paths all begin with one, which would otherwise produce
        // "//bench/static" — legal, served, and different from every other
        // run's URL for no reason anyone chose.
        $base = rtrim($this->targetUrl, '/');

        return $base.HttpBenchmarkResults::ROUTES[$route].($route === 'io' ? '?ms='.$this->ioMs : '');
    }

    /**
     * The rate the latency pass offers, as a fraction of the best throughput
     * the sweep actually reached.
     *
     * Staying below the maximum is the whole point. At saturation every client
     * is queueing and the percentiles describe the backlog; below it they
     * describe the server. Seventy percent is busy enough to be realistic and
     * has enough margin that a slightly lucky sweep window does not produce a
     * rate the server cannot hold.
     */
    public function latencyRate(float $bestRps): int
    {
        return max(1, (int) round($bestRps * $this->latencyLoad));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_url' => $this->targetUrl,
            'target_mode' => $this->targetMode,
            'io_ms' => $this->ioMs,
            'cores' => $this->cores,
            'workers' => $this->workers,
            'levels' => $this->levels,
            'warmup_seconds' => $this->warmupSeconds,
            'level_seconds' => $this->levelSeconds,
            'latency_seconds' => $this->latencySeconds,
            'latency_load' => $this->latencyLoad,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        return new self(
            targetUrl: $state['target_url'],
            targetMode: $state['target_mode'],
            ioMs: (int) $state['io_ms'],
            cores: $state['cores'] === null ? null : (int) $state['cores'],
            workers: $state['workers'] === null ? null : (int) $state['workers'],
            levels: array_map('intval', $state['levels']),
            warmupSeconds: (int) $state['warmup_seconds'],
            levelSeconds: (int) $state['level_seconds'],
            latencySeconds: (int) $state['latency_seconds'],
            latencyLoad: (float) $state['latency_load'],
        );
    }
}
