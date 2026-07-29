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
    ];

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
     * @param  array{url: string, mode: string}  $target
     */
    public function writeMeta(array $target, int $duration, int $connections): void
    {
        File::ensureDirectoryExists(dirname($this->metaPath()));
        File::put($this->metaPath(), json_encode([
            'target' => $target['url'],
            'mode' => $target['mode'],
            'duration_seconds' => $duration,
            'connections' => $connections,
        ]));
    }

    /**
     * @return array{mode: string|null, target: string|null, duration_seconds: int|null, connections: int|null, routes: array<string, array<string, mixed>>}|null
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
                'success_rate' => $data['summary']['successRate'] ?? null,
                'p50_ms' => $this->toMilliseconds($data['latencyPercentiles']['p50'] ?? null),
                'p95_ms' => $this->toMilliseconds($data['latencyPercentiles']['p95'] ?? null),
                'p99_ms' => $this->toMilliseconds($data['latencyPercentiles']['p99'] ?? null),
                'status_codes' => $data['statusCodeDistribution'] ?? [],
            ];
        }

        if ($routes === []) {
            return null;
        }

        $meta = $this->readJson($this->metaPath()) ?? [];

        return [
            'mode' => $meta['mode'] ?? null,
            'target' => $meta['target'] ?? null,
            'duration_seconds' => $meta['duration_seconds'] ?? null,
            'connections' => $meta['connections'] ?? null,
            'routes' => $routes,
        ];
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
