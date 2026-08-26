<?php

namespace App\Actions\Results;

use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadStep;
use App\Support\Http\StepResult;
use Illuminate\Support\Facades\File;

/**
 * Parses the per-route oha JSON files written by the HTTP self-test.
 * oha reports latencies in seconds; they are converted to milliseconds.
 */
class HttpBenchmarkResults extends BenchmarkResults
{
    /**
     * @var array<string, string>
     */
    public const ROUTES = [
        'static' => '/bench/static',
        'json' => '/bench/json',
        'db_read' => '/bench/db-read',
        'io' => '/bench/io',
    ];

    /**
     * How close to a computed ceiling counts as having reached it. A saturating
     * route never quite touches its theoretical maximum — there is always some
     * per-request overhead on top of the sleep — so a run landing within this
     * much of the limit is at it.
     */
    protected const AT_CEILING = 0.85;

    public function metaPath(): string
    {
        return $this->resultsPath('http-meta.json');
    }

    public function routePath(string $key): string
    {
        return $this->resultsPath('http-'.str_replace('_', '-', $key).'.json');
    }

    /**
     * One level of a route's sweep.
     *
     * The concurrency is in the filename rather than an index, so a directory
     * of results is readable without the plan that produced it — which matters
     * because these files are what an interrupted run leaves behind.
     */
    public function sweepPath(string $key, int $connections): string
    {
        return $this->resultsPath(sprintf('http-%s-c%d.json', str_replace('_', '-', $key), $connections));
    }

    /**
     * A route's open-loop response-time pass, kept separate from the sweep
     * because it measures a different thing: the sweep answers how much the
     * server can take, this answers what a visitor experiences while it is
     * busy. Reporting them from one file was the whole problem.
     */
    public function latencyPath(string $key): string
    {
        return $this->resultsPath('http-'.str_replace('_', '-', $key).'-latency.json');
    }

    /**
     * The name a single measured window is addressed by.
     *
     * One string identifies a step in the results directory, in the upload
     * URL, and in the pairing's record of what has landed — so the three
     * cannot disagree about which window arrived. Dashes throughout, matching
     * the route paths rather than the internal keys.
     */
    public static function slotFor(string $route, string $phase, int $connections): ?string
    {
        $route = str_replace('_', '-', $route);

        return match ($phase) {
            LoadStep::PHASE_SWEEP => sprintf('%s-c%d', $route, $connections),
            LoadStep::PHASE_LATENCY => $route.'-latency',
            default => null,
        };
    }

    /**
     * Where a slot's result belongs, or null when the name is not one this run
     * could have asked for.
     */
    public function pathForSlot(string $slot): ?string
    {
        if (! preg_match('/^([a-z-]+)-(?:c([0-9]{1,4})|latency)$/', $slot, $matches)) {
            return null;
        }

        $key = str_replace('-', '_', $matches[1]);

        if (! array_key_exists($key, self::ROUTES)) {
            return null;
        }

        return isset($matches[2]) && $matches[2] !== ''
            ? $this->sweepPath($key, (int) $matches[2])
            : $this->latencyPath($key);
    }

    /**
     * Every window the sweep half of a run is waiting on.
     *
     * @param  array<int, int>  $levels
     * @return array<int, string>
     */
    public static function sweepSlots(array $levels): array
    {
        $slots = [];

        foreach ($levels as $key => $routeLevels) {
            foreach ((array) $routeLevels as $connections) {
                $slots[] = self::slotFor($key, LoadStep::PHASE_SWEEP, (int) $connections);
            }
        }

        return $slots;
    }

    /**
     * How long the server itself took, with the path to it taken out.
     *
     * At one connection nothing is queued, so the response time is the
     * server's own work plus the round trip — and the round trip was measured
     * before any load started. What is left is the only figure that can size
     * the sweep, because it is the one the worker pool is actually spending.
     */
    public function probeServiceMs(string $route, ?float $rttMs): ?float
    {
        $data = $this->readJson($this->sweepPath($route, LoadProfile::PROBE_CONCURRENCY));

        if ($data === null || ! $this->hasMeasuredTraffic($data)) {
            return null;
        }

        $observed = StepResult::fromOha($data)->p50Ms;

        return $observed === null ? null : max(0.0, $observed - (float) ($rttMs ?? 0));
    }

    /**
     * Record what the probe decided, so the sweep, the wait, and the parser
     * all read the same levels rather than each deriving their own.
     *
     * @param  array<string, array<int, int>>  $levels
     */
    public function writeLevels(array $levels, ?int $requiredConcurrency = null): void
    {
        $meta = $this->readMeta() ?? [];
        $meta['levels'] = $levels;
        $meta['required_concurrency'] = $requiredConcurrency;

        File::put($this->metaPath(), json_encode($meta));
    }

    /**
     * The load settings this run was started with, or null when the stage
     * never wrote them.
     *
     * @return array<string, mixed>|null
     */
    public function readMeta(): ?array
    {
        return $this->readJson($this->metaPath());
    }

    /**
     * Remove every result a previous run left for these routes.
     *
     * In external mode a result file's existence is the signal that the
     * generator uploaded it during this run, and the results directory
     * deliberately survives between runs — so a stale file would satisfy the
     * wait and publish last week's numbers as this run's.
     *
     * Matched by shape rather than by a list of levels: the sweep is sized
     * from what the probe measures, so the levels a previous run used are not
     * knowable before this one starts.
     */
    public function clearRouteResults(): void
    {
        foreach (array_keys(self::ROUTES) as $key) {
            $route = str_replace('_', '-', $key);

            foreach (File::glob($this->resultsPath('http-'.$route.'-*.json')) as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * Persist the load settings the run actually used so execute() can
     * report them alongside the per-route results.
     *
     * $workers is how many requests the server will process at once, when the
     * environment exposes a number — an FPM pool size, a FrankenPHP thread
     * count, an Octane worker count. It belongs with the load settings because
     * it caps them: a request occupies a worker for its whole duration, so
     * /bench/io — which sleeps io_ms to model an outbound call — can never
     * exceed workers / io_ms requests per second no matter how fast the box is.
     * Recording it is what lets a reader tell a framework measurement from a
     * concurrency-ceiling measurement.
     *
     * $generator records where the load came from: mode "self" when this
     * machine drove its own load (the default, and the only possibility for
     * runs written before the block existed), mode "external" when a second
     * machine drove it. The rest of the block describes that machine — it is
     * measurement conditions, and it travels with the numbers it conditions.
     *
     * The concurrency levels are recorded because they are host-dependent —
     * derived from this machine's cores and workers — so nothing reading a run
     * may assume which levels were measured.
     *
     * @param  array{url: string, mode: string}  $target
     * @param  array{mode: string, rtt_ms: float|null, source_ip: string|null, oha_version: string|null, host: string|null, fd_limit?: int|null}|null  $generator
     */
    public function writeMeta(array $target, LoadProfile $profile, ?int $workers = null, ?array $generator = null): void
    {
        File::ensureDirectoryExists(dirname($this->metaPath()));
        File::put($this->metaPath(), json_encode([
            'target' => $target['url'],
            'mode' => $target['mode'],
            // Both container-internal ports resolve to mode "loopback", but one
            // is plaintext on 8080 and the other terminates TLS on 8443. A
            // handshake and per-request encryption on every one of a hundred
            // thousand requests is a large, entirely invisible difference
            // between two runs that otherwise describe themselves identically.
            'tls' => str_starts_with($target['url'], 'https://'),
            'io_ms' => $profile->ioMs,
            'workers' => $workers,
            'cores' => $profile->cores,
            // Seeded with the fallback the host's cores and workers imply, and
            // replaced once the probe has measured what the server actually
            // costs. Per route, because service time differs by two orders of
            // magnitude between a static response and a 100ms sleep.
            'levels' => array_fill_keys(array_keys(self::ROUTES), $profile->levels),
            'level_seconds' => $profile->levelSeconds,
            'latency_seconds' => $profile->latencySeconds,
            'latency_load' => $profile->latencyLoad,
            'generator' => $generator ?? self::selfGenerator(),
        ]));
    }

    /**
     * Merge generator details into an already-written meta file. The external
     * stage writes its meta before the generator has necessarily handshaked,
     * so the description arrives later than the settings do.
     *
     * @param  array<string, mixed>  $generator
     */
    public function mergeGeneratorMeta(array $generator): void
    {
        $meta = $this->readJson($this->metaPath());

        if ($meta === null) {
            return;
        }

        $meta['generator'] = array_merge($meta['generator'] ?? self::selfGenerator(), $generator);

        File::put($this->metaPath(), json_encode($meta));
    }

    /**
     * @return array{mode: string, rtt_ms: null, source_ip: null, oha_version: null, host: null}
     */
    public static function selfGenerator(): array
    {
        return [
            'mode' => 'self',
            'rtt_ms' => null,
            'source_ip' => null,
            'oha_version' => null,
            'host' => null,
        ];
    }

    /**
     * @return array{mode: string|null, target: string|null, duration_seconds: int|null, connections: int|null, io_ms: int|null, routes: array<string, array<string, mixed>>}|null
     */
    public function execute(): ?array
    {
        $meta = $this->readMeta() ?? [];
        $levels = $meta['levels'] ?? [];
        // Runs written before the rename carry the FPM-specific key. Reading
        // both keeps an existing results directory parseable.
        $workers = $meta['workers'] ?? $meta['fpm_max_children'] ?? null;
        $workers = is_numeric($workers) ? (int) $workers : null;
        $ioMs = isset($meta['io_ms']) ? (int) $meta['io_ms'] : null;

        $routes = [];
        $curves = [];

        foreach (self::ROUTES as $key => $path) {
            $curve = $this->curveFor($key, array_map('intval', (array) ($levels[$key] ?? [])));

            if ($curve === null) {
                continue;
            }

            $curves[$key] = $curve;
            $routes[$key] = $this->routePayload($key, $path, $curve);
        }

        if ($routes === []) {
            return null;
        }

        return [
            'mode' => $meta['mode'] ?? null,
            'target' => $meta['target'] ?? null,
            'tls' => $meta['tls'] ?? null,
            'io_ms' => $ioMs,
            'workers' => $workers,
            'cores' => isset($meta['cores']) ? (int) $meta['cores'] : null,
            'levels' => $levels,
            'required_concurrency' => $meta['required_concurrency'] ?? null,
            'level_seconds' => $meta['level_seconds'] ?? null,
            'latency_seconds' => $meta['latency_seconds'] ?? null,
            'latency_load' => $meta['latency_load'] ?? null,
            'generator' => $this->generator($meta, $curves),
            'pool_ceiling' => $this->poolCeiling($curves, $workers, $ioMs),
            'generator_bound' => $this->isGeneratorBound($curves),
            'routes' => $routes,
        ];
    }

    /**
     * Read back one route's sweep, or null when nothing measured it.
     *
     * A level with no file is skipped rather than treated as a zero: an
     * interrupted run leaves gaps, and a gap is missing data, not a server
     * that served nothing.
     *
     * @param  array<int, int>  $levels
     */
    public function curveFor(string $key, array $levels): ?LoadCurve
    {
        $measurements = [];

        foreach ($levels as $connections) {
            $data = $this->readJson($this->sweepPath($key, $connections));

            if ($data === null || ! $this->hasMeasuredTraffic($data)) {
                continue;
            }

            $measurements[] = ['concurrency' => $connections, 'result' => StepResult::fromOha($data)];
        }

        return $measurements === [] ? null : new LoadCurve($key, $measurements);
    }

    /**
     * One route as the results document publishes it.
     *
     * Throughput and response time come from different measurements on
     * purpose. The sweep answers how much the server can take, and its
     * percentiles describe a queue. The open-loop pass answers what a visitor
     * experiences at a rate the server can hold. Reporting both from one
     * saturated window is what this replaced.
     *
     * @return array<string, mixed>
     */
    protected function routePayload(string $key, string $path, LoadCurve $curve): array
    {
        $peak = $curve->bestResult();

        return [
            'path' => $path,
            'throughput' => $peak === null ? null : [
                'requests_per_second' => $peak->requestsPerSecond,
                // Where the peak actually happened. The knee — the cheapest
                // level within a few percent of it — is what the response-time
                // pass holds open, and is recorded beside it rather than
                // instead of it.
                'concurrency' => $curve->peakConcurrency(),
                'knee_concurrency' => $curve->knee(),
                'success_rate' => $peak->successRate,
                'total_requests' => $peak->totalRequests,
                'elapsed_seconds' => $peak->elapsedSeconds,
                'status_codes' => $peak->statusCodes,
                'saturated' => $curve->isSaturated(),
            ],
            'latency' => $this->latencyPayload($key),
            'curve' => $curve->points(),
            'breaking_point' => $curve->breakingPoint(),
        ];
    }

    /**
     * The open-loop pass, or null when the route never earned one.
     *
     * requested_rps against achieved_rps is recorded rather than reduced to a
     * single figure: when they diverge the server could not hold the rate it
     * was offered, so the percentiles include a backlog — and that divergence
     * is itself the finding.
     *
     * @return array<string, mixed>|null
     */
    protected function latencyPayload(string $key): ?array
    {
        $data = $this->readJson($this->latencyPath($key));

        if ($data === null || ! $this->hasMeasuredTraffic($data)) {
            return null;
        }

        $result = StepResult::fromOha($data);

        return [
            'achieved_rps' => $result->requestsPerSecond,
            'p50_ms' => $result->p50Ms,
            'p90_ms' => $result->p90Ms,
            'p95_ms' => $result->p95Ms,
            'p99_ms' => $result->p99Ms,
            'success_rate' => $result->successRate,
            'total_requests' => $result->totalRequests,
            'elapsed_seconds' => $result->elapsedSeconds,
            'corrected' => true,
        ];
    }

    /**
     * What the /bench/io route says about the worker pool.
     *
     * This is arithmetic offered next to a measurement, not a verdict. A
     * request on that route holds a worker for its whole simulated wait, so
     * `workers x 1000/io_ms` is the most the pool can serve however fast the
     * machine is. Publishing the prediction alongside where the curve actually
     * bent lets the results page draw a line and have the measurement land on
     * it — which is how a reader recognises their own worker count without
     * being told what a worker pool is.
     *
     * @param  array<string, LoadCurve>  $curves
     * @return array<string, mixed>|null
     */
    protected function poolCeiling(array $curves, ?int $workers, ?int $ioMs): ?array
    {
        $curve = $curves['io'] ?? null;

        if ($curve === null || $workers === null || $ioMs === null || $ioMs <= 0) {
            return null;
        }

        $predicted = round($workers * (1000 / $ioMs), 1);

        return [
            'workers' => $workers,
            'io_ms' => $ioMs,
            'predicted_rps' => $predicted,
            'observed_rps' => $curve->bestRps(),
            'knee_concurrency' => $curve->knee(),
            'at_ceiling' => $curve->bestRps() >= $predicted * self::AT_CEILING,
        ];
    }

    /**
     * Whether the machine driving the load was the limit rather than the one
     * serving it.
     *
     * The sweep makes this far more answerable than a single fixed window did.
     * In a closed loop each connection holds one request at a time, so
     * concurrency = rate x response time is an identity; a level where that
     * does not hold had connections sitting idle, which is the generator
     * running out of capacity rather than the server.
     *
     * @param  array<string, LoadCurve>  $curves
     */
    protected function isGeneratorBound(array $curves): ?bool
    {
        if ($curves === []) {
            return null;
        }

        foreach ($curves as $curve) {
            if ($curve->generatorStarved()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where the load came from, filled in from the sweep when it was this
     * machine's own.
     *
     * A self-test has nothing to measure the round trip up front, so it is
     * taken afterwards from the single-connection level, where nothing is
     * queued. The old figure came from the fastest request inside a saturated
     * window, where even the quickest observation had waited behind something.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, LoadCurve>  $curves
     * @return array<string, mixed>
     */
    protected function generator(array $meta, array $curves): array
    {
        $generator = ($meta['generator'] ?? null) ?: self::selfGenerator();

        if (($generator['mode'] ?? 'self') === 'self' && ($generator['rtt_ms'] ?? null) === null) {
            $floors = array_filter(array_map(fn (LoadCurve $curve): ?float => $curve->rttFloorMs(), $curves));
            $generator['rtt_ms'] = $floors === [] ? null : min($floors);
        }

        return $generator;
    }

    public function detail(string $slot, string $path): ?array
    {
        $data = $this->readJson($path);

        if ($data === null || ! $this->hasMeasuredTraffic($data)) {
            return null;
        }

        $summary = $data['summary'] ?? [];
        $percentiles = $data['latencyPercentiles'] ?? [];
        $statusCodes = $data['statusCodeDistribution'] ?? [];

        return [
            'path' => $slot,
            'requests_per_second' => round($summary['requestsPerSec'] ?? 0, 1),
            'total_requests' => (int) array_sum($statusCodes),
            'duration_seconds' => $summary['total'] ?? null,
            'success_rate' => $summary['successRate'] ?? null,
            'bytes_per_second' => $summary['sizePerSec'] ?? null,
            'total_bytes' => $summary['totalData'] ?? null,
            'average_ms' => $this->toMilliseconds($summary['average'] ?? null),
            'fastest_ms' => $this->toMilliseconds($summary['fastest'] ?? null),
            'slowest_ms' => $this->toMilliseconds($summary['slowest'] ?? null),
            'latency_ms' => [
                'p50' => $this->toMilliseconds($percentiles['p50'] ?? null),
                'p90' => $this->toMilliseconds($percentiles['p90'] ?? null),
                'p95' => $this->toMilliseconds($percentiles['p95'] ?? null),
                'p99' => $this->toMilliseconds($percentiles['p99'] ?? null),
            ],
            'status_codes' => $statusCodes,
            'errors' => $data['errorDistribution'] ?? [],
        ];
    }

    protected function toMilliseconds(?float $seconds): ?float
    {
        return $seconds === null ? null : round($seconds * 1000, 2);
    }

    protected function rounded(mixed $value, int $precision): ?float
    {
        return is_numeric($value) ? round((float) $value, $precision) : null;
    }

    /**
     * Every bench route returns a non-empty body, so an oha run reporting
     * zero bytes transferred never reached the application (e.g. a web
     * server answering empty 200s without invoking PHP) and its throughput
     * numbers are meaningless. Older result files without totalData are
     * accepted as-is.
     *
     * @param  array<string, mixed>  $data
     */
    protected function hasMeasuredTraffic(array $data): bool
    {
        $totalData = $data['summary']['totalData'] ?? null;

        return $totalData === null || $totalData > 0;
    }
}
