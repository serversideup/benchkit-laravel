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

    protected function toMilliseconds(?float $seconds): ?float
    {
        return $seconds === null ? null : round($seconds * 1000, 2);
    }
}
