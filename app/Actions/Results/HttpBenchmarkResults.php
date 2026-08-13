<?php

namespace App\Actions\Results;

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
     * @param  array{url: string, mode: string}  $target
     */
    public function writeMeta(array $target, int $duration, int $connections, int $ioMs, ?int $workers = null): void
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
            'duration_seconds' => $duration,
            'connections' => $connections,
            'io_ms' => $ioMs,
            'workers' => $workers,
        ]));
    }

    /**
     * @return array{mode: string|null, target: string|null, duration_seconds: int|null, connections: int|null, io_ms: int|null, routes: array<string, array<string, mixed>>}|null
     */
    public function execute(): ?array
    {
        $routes = [];

        foreach (self::ROUTES as $key => $path) {
            $data = $this->readJson($this->routePath($key));

            if ($data === null || ! $this->hasMeasuredTraffic($data)) {
                continue;
            }

            $routes[$key] = [
                'path' => $path,
                'requests_per_second' => round($data['summary']['requestsPerSec'] ?? 0, 1),
                // What the load generator observed, against duration_seconds
                // below, which is what the run asked for. They come apart when
                // a route is slow enough that in-flight requests outlive the
                // window, and a throughput figure divided by the wrong number
                // of seconds is worth being able to notice.
                'elapsed_seconds' => $this->rounded($data['summary']['total'] ?? null, 2),
                'success_rate' => $data['summary']['successRate'] ?? null,
                'p50_ms' => $this->toMilliseconds($data['latencyPercentiles']['p50'] ?? null),
                'p95_ms' => $this->toMilliseconds($data['latencyPercentiles']['p95'] ?? null),
                'p99_ms' => $this->toMilliseconds($data['latencyPercentiles']['p99'] ?? null),
                'total_requests' => (int) array_sum($data['statusCodeDistribution'] ?? []),
                'status_codes' => $data['statusCodeDistribution'] ?? [],
            ];
        }

        if ($routes === []) {
            return null;
        }

        $meta = $this->readJson($this->metaPath()) ?? [];
        // Runs written before the rename carry the FPM-specific key. Reading
        // both keeps an existing results directory parseable.
        $workers = $meta['workers'] ?? $meta['fpm_max_children'] ?? null;
        $workers = is_numeric($workers) ? (int) $workers : null;
        $connections = isset($meta['connections']) ? (int) $meta['connections'] : null;
        $ioMs = isset($meta['io_ms']) ? (int) $meta['io_ms'] : null;

        return [
            'mode' => $meta['mode'] ?? null,
            'target' => $meta['target'] ?? null,
            'tls' => $meta['tls'] ?? null,
            'duration_seconds' => $meta['duration_seconds'] ?? null,
            'connections' => $meta['connections'] ?? null,
            'io_ms' => $meta['io_ms'] ?? null,
            'workers' => $workers,
            'oversubscribed' => $this->isOversubscribed($connections, $workers),
            'pool_limited' => $this->isPoolLimited($routes, $workers, $ioMs),
            'routes' => $routes,
        ];
    }

    /**
     * Whether the load held open more connections than the server can work on
     * at once, so requests spent time queued before being served.
     *
     * This is a fact about the load, not a defect: a saturation test is meant
     * to offer more work than the server can take, because that is how a
     * maximum is found. What it changes is how the latency figures read — they
     * include the wait, so they describe the queue rather than the work.
     */
    protected function isOversubscribed(?int $connections, ?int $workers): ?bool
    {
        if ($connections === null || $workers === null || $workers <= 0) {
            return null;
        }

        return $connections > $workers;
    }

    /**
     * Whether the worker count — rather than the CPU — is what actually capped
     * this run.
     *
     * This used to be `connections > workers`, which is a comparison of two
     * settings and not evidence of anything. On a two-core box with twenty
     * workers it reported every run as pool-bound while the real ceiling was
     * the CPU: throughput of 477 req/s across 2 cores is 4ms of CPU per
     * request against 42ms of worker occupancy, so the workers were mostly
     * waiting for a core. Adding workers there makes latency worse and
     * throughput no better, which is the opposite of what the warning advised.
     *
     * The I/O route is the one place the ceiling can actually be computed. Its
     * service time is a known sleep, so a worker can serve at most 1000/io_ms
     * requests per second and the pool multiplies out to a hard limit. Landing
     * at that limit is evidence; exceeding a connection count is not.
     *
     * @param  array<string, array<string, mixed>>  $routes
     */
    protected function isPoolLimited(array $routes, ?int $workers, ?int $ioMs): ?bool
    {
        $observed = $routes['io']['requests_per_second'] ?? null;

        if ($workers === null || $workers <= 0 || ! $ioMs || $observed === null) {
            return null;
        }

        return $observed >= ($workers * (1000 / $ioMs)) * self::AT_CEILING;
    }

    /**
     * Full per-route detail for the live console summary, normalised from a
     * single oha JSON file. Latencies are converted to milliseconds. Returns
     * null when the file is missing or unreadable.
     *
     * @return array{
     *     path: string,
     *     requests_per_second: float,
     *     total_requests: int,
     *     duration_seconds: float|null,
     *     success_rate: float|null,
     *     bytes_per_second: float|null,
     *     total_bytes: int|null,
     *     average_ms: float|null,
     *     fastest_ms: float|null,
     *     slowest_ms: float|null,
     *     latency_ms: array{p50: float|null, p90: float|null, p95: float|null, p99: float|null},
     *     status_codes: array<string, int>,
     *     errors: array<string, int>
     * }|null
     */
    public function detail(string $key): ?array
    {
        $data = $this->readJson($this->routePath($key));

        if ($data === null || ! $this->hasMeasuredTraffic($data)) {
            return null;
        }

        $summary = $data['summary'] ?? [];
        $percentiles = $data['latencyPercentiles'] ?? [];
        $statusCodes = $data['statusCodeDistribution'] ?? [];

        return [
            'path' => self::ROUTES[$key] ?? $key,
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
