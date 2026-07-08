<?php

namespace App\Actions\Results;

/**
 * Parses the per-route oha JSON files written by the HTTP self-test.
 * oha reports latencies in seconds; they are converted to milliseconds.
 */
class HttpBenchmarkResults
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
        return config('benchmark.results_path').'/http-meta.json';
    }

    public function routePath(string $key): string
    {
        return config('benchmark.results_path').'/http-'.str_replace('_', '-', $key).'.json';
    }

    /**
     * @return array{mode: string|null, target: string|null, duration_seconds: int|null, connections: int|null, routes: array<string, array<string, mixed>>}|null
     */
    public function execute(): ?array
    {
        $routes = [];

        foreach (self::ROUTES as $key => $path) {
            $file = $this->routePath($key);

            if (! file_exists($file)) {
                continue;
            }

            $data = json_decode(file_get_contents($file), true);

            if (! is_array($data)) {
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

        $meta = file_exists($this->metaPath())
            ? (json_decode(file_get_contents($this->metaPath()), true) ?: [])
            : [];

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
        $file = $this->routePath($key);

        if (! file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (! is_array($data)) {
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
}
