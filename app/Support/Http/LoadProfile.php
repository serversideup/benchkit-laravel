<?php

namespace App\Support\Http;

use App\Actions\Results\HttpBenchmarkResults;

/**
 * The load a run will offer, decided before any of it runs.
 *
 * Every renderer builds from one of these — the self-test chain and the
 * external generator's script — so the load cannot drift between the two ways
 * it can be driven.
 */
class LoadProfile
{
    /** Spread wide rather than fine: with nothing to centre on, the job is to bracket the plateau. */
    public const BLIND_LEVELS = [1, 8, 32, 128, 256];

    /** Nothing is measured above this, whatever the host suggests. */
    public const MAX_CONCURRENCY = 512;

    /** Descriptors left for the shell, curl, the result file, and whatever the OS holds open. */
    protected const DESCRIPTOR_HEADROOM = 64;

    /** Levels per route. Each one costs levelSeconds of every run. */
    public const MAX_LEVELS = 6;

    /** How far past the bend the top level sits, so the plateau has points on it. */
    protected const OVERSUBSCRIPTION = 4;

    /** Stands in for the pool when the host exposed neither a core count nor a worker count. */
    protected const BLIND_PARALLELISM = 8;

    /**
     * Slots per core assumed for a sleeping route when the runtime declares no
     * worker count.
     *
     * Only a worker-mode runtime leaves it undeclared — a process-per-request
     * pool is read from pm.max_children — and multiplexing past the core count
     * is what worker mode is for, so the cores alone are a floor rather than an
     * estimate. FrankenPHP, the runtime this applies to in practice, defaults
     * to two threads per core.
     */
    protected const BLIND_SLOTS_PER_CORE = 2;

    /** One connection, nothing queued: the only measurement that separates the server from the path. */
    public const PROBE_CONCURRENCY = 1;

    /** At or below this a measured service time is noise, not a number. */
    public const MIN_SERVICE_MS = 0.05;

    /**
     * The most of a request that may be attributed to the path rather than the
     * server. Past this a connection is measuring the network.
     *
     * Kept apart from MAX_CONCURRENCY: a level count and a ratio sharing one
     * constant hid a run whose inflation landed exactly on the level ceiling.
     */
    public const MAX_INFLATION = 100.0;

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
     * Settles the address once, at the handshake, while leaving the URL alone
     * so the Host header and TLS name stay what a real client would send.
     * Without it every window resolves again, and a resolver worn down by the
     * run fails the rest of it with an error about DNS.
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
     * A connection is an open file, and a descriptor limit is not enforced
     * politely: asking for more than the generator can hold produces a fast
     * wrong result rather than a slow one, because connections that fail to
     * open are counted as completed requests.
     */
    public function ceiling(): int
    {
        return self::ceilingFor($this->fdLimit);
    }

    public static function ceilingFor(?int $fdLimit): int
    {
        if ($fdLimit === null) {
            return self::MAX_CONCURRENCY;
        }

        return max(2, min(self::MAX_CONCURRENCY, $fdLimit - self::DESCRIPTOR_HEADROOM));
    }

    /**
     * @param  array{url: string, mode: string}  $target
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $target, array $settings, ?int $cores, ?int $workers): self
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
        );
    }

    /**
     * How much of a request is spent somewhere other than the server.
     *
     * A connection holds one request at a time, so keeping N in flight from a
     * distance takes N x (total / service) connections. On loopback the ratio
     * is about 1; over a network it is not.
     *
     * $rttMs is the transport round trip — a full-request round trip would
     * double-count the server's own work.
     */
    public static function inflation(?float $serviceMs, ?float $rttMs): float
    {
        $service = max(self::MIN_SERVICE_MS, (float) $serviceMs);
        $rtt = max(0.0, (float) $rttMs);

        return $service <= 0 ? 1.0 : min(self::MAX_INFLATION, ($service + $rtt) / $service);
    }

    /**
     * How many requests this server can have in flight on a route of this kind.
     *
     * A request holds a worker for its whole life but a core only while it is
     * computing, so the two ceilings bind different routes. The I/O route
     * sleeps and bends at the worker count; every other route computes and
     * cannot run more at once than there are cores. That matters because the
     * pool is sized from memory, so a large host reports one no amount of
     * concurrency could occupy.
     */
    public function parallelism(?string $route = null): ?int
    {
        if ($route === HttpBenchmarkResults::IO_ROUTE) {
            // A sleeping route is bounded by slots, not by cores, so an
            // undeclared pool cannot be read as the core count: sized that way
            // on a 64-core host the sweep stopped at 257 connections while the
            // runtime held about 128, leaving one measured point past the bend
            // and no way to show the curve flatten.
            return $this->workers ?? ($this->cores === null ? null : $this->cores * self::BLIND_SLOTS_PER_CORE);
        }

        if ($this->cores !== null && $this->workers !== null) {
            return min($this->cores, $this->workers);
        }

        return $this->cores ?? $this->workers;
    }

    /**
     * The levels a route is swept at, sized from what its probe measured.
     *
     * Per route, because service time sets them and differs by orders of
     * magnitude: one shared ladder would either miss the fast routes' bend or
     * spend the run queueing on the sleeping one. Falls back to the host ladder
     * when the probe resolved nothing, since an unresolved figure sizes nothing.
     *
     * @return array<int, int>
     */
    public function levelsFor(?float $serviceMs, ?float $rttMs, ?string $route = null): array
    {
        if ($serviceMs === null) {
            return self::levels($this->cores, $this->workers);
        }

        $inflation = self::inflation($serviceMs, $rttMs);
        $top = $this->oversubscribedTop($route, $inflation);

        // Geometric rather than scaled-and-clamped: clamping collapses every
        // level onto the ceiling once the inflation is large, leaving a route
        // with one point where its curve should be.
        $candidates = [self::PROBE_CONCURRENCY];
        $steps = self::MAX_LEVELS - 1;

        for ($step = 1; $step <= $steps; $step++) {
            $candidates[] = max(2, (int) round($top ** ($step / $steps)));
        }

        $candidates = $this->seedPoolBend($candidates, $route, $inflation);

        $levels = array_values(array_unique($candidates));
        sort($levels);

        return $levels;
    }

    /**
     * Far enough past the bend that the curve has room to flatten rather than
     * ending while it still rises.
     */
    protected function oversubscribedTop(?string $route, float $inflation): int
    {
        $parallelism = $this->parallelism($route) ?? self::BLIND_PARALLELISM;

        return min($this->ceiling(), max(2, (int) round($parallelism * self::OVERSUBSCRIPTION * $inflation)));
    }

    /**
     * Swap the level nearest the predicted bend for the bend itself, keeping
     * the ladder the same length.
     *
     * Worth a level rather than bracketing: the bend is the one figure on the
     * results page a reader should recognise as their own setting. Not at the
     * pool size exactly, because holding the pool busy from a distance takes
     * that many connections times the inflation.
     *
     * @param  array<int, int>  $candidates
     * @return array<int, int>
     */
    protected function seedPoolBend(array $candidates, ?string $route, float $inflation): array
    {
        $parallelism = $this->parallelism($route);

        if ($parallelism === null || $parallelism < 1) {
            return $candidates;
        }

        $bend = min($this->ceiling(), max(2, (int) round($parallelism * $inflation)));
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
     * The concurrency levels this host is measured at when no probe sized them.
     *
     * A machine has two ceilings and different routes hit different ones: the
     * computing routes flatten once the cores are busy, and the sleeping route
     * is bounded by how many workers exist rather than how fast they are. A
     * ladder seeded from one steps straight over the other.
     *
     * Derived from the machine rather than fixed, because one connection count
     * cannot be the same test on a one-core box and a thirty-two-core box.
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

        // Clamped rather than discarded: dropping everything above the ceiling
        // leaves the top level sitting on the pool size, and a plateau needs a
        // point on the far side of the bend.
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
     * Levels are read back rather than recomputed, so a driver cannot disagree
     * with the file the results are being written into.
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
            warmupSeconds: (int) ($meta['warmup_seconds'] ?? 3),
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
     * External mode reaches the machine through its public URL, but everything
     * else has to stay identical or the two modes stop being the same test.
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
     * Staying below the maximum is the whole point: at saturation every client
     * is queueing and the percentiles describe the backlog. The fraction leaves
     * margin so a lucky sweep window cannot set a rate the server will not hold.
     */
    public function latencyRate(float $peakRps): int
    {
        return max(1, (int) round($peakRps * $this->latencyLoad));
    }
}
