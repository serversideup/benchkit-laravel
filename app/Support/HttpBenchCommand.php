<?php

namespace App\Support;

use App\Actions\Results\HttpBenchmarkResults;

/**
 * The single source of truth for the standard HTTP load. plan() describes it
 * one route at a time; every renderer — the local self-test chain, the
 * external generator script, the copy-paste commands in the UI — consumes the
 * same plan, so the load cannot drift between the ways it can be driven.
 */
class HttpBenchCommand
{
    /** Throwaway load before each measured window; discarded, never parsed. */
    public const WARMUP_SECONDS = 3;

    /**
     * One descriptor per route, in measurement order.
     *
     * load_flags shape the load (connections, redirects, TLS trust) and must
     * be identical wherever the load runs; capture_flags are output plumbing
     * for a run whose JSON is kept.
     *
     * @param  array{url: string, mode: string}  $target
     * @return array<int, array{key: string, path: string, url: string, banner: string, connections: int, load_flags: string, capture_flags: string, warmup_seconds: int, duration_seconds: int}>
     */
    public function plan(array $target, int $duration, int $connections, int $ioMs): array
    {
        $plan = [];
        $position = 0;
        $total = count(HttpBenchmarkResults::ROUTES);

        foreach (HttpBenchmarkResults::ROUTES as $key => $path) {
            $position++;

            // The /bench/io route sleeps ?ms to model one outbound dependency
            // call; every other route measures the framework path as-is.
            $url = $target['url'].$path.($key === 'io' ? '?ms='.$ioMs : '');
            $insecure = str_starts_with($url, 'https://') ? ' --insecure' : '';

            $plan[] = [
                'key' => $key,
                'path' => $path,
                'url' => $url,
                'banner' => "Load testing {$path} ({$position} of {$total}) — {$duration}s at {$connections} connections against {$target['url']} [{$target['mode']}]",
                'connections' => $connections,
                'load_flags' => sprintf('-c %d --redirect 0%s', $connections, $insecure),
                'capture_flags' => '--no-tui --output-format json',
                'warmup_seconds' => self::WARMUP_SECONDS,
                'duration_seconds' => $duration,
            ];
        }

        return $plan;
    }

    /**
     * The local self-test: each route is load tested in sequence, written to
     * its own JSON file, and summarised for the live console before the next
     * route starts.
     *
     * @param  array{url: string, mode: string}  $target
     */
    public function build(array $target, int $duration, int $connections, int $ioMs): string
    {
        $bin = base_path('vendor/bin/oha');
        $results = new HttpBenchmarkResults;

        $steps = [];

        foreach ($this->plan($target, $duration, $connections, $ioMs) as $route) {
            $flags = $route['load_flags'].' '.$route['capture_flags'];

            $steps[] = sprintf('echo %s', escapeshellarg($route['banner']));
            // Warm up (discarded), then measure into the route's JSON file.
            $steps[] = sprintf('%s -z %ds %s %s > /dev/null 2>&1', $bin, $route['warmup_seconds'], $flags, escapeshellarg($route['url']));
            $steps[] = sprintf('%s -z %ds %s %s > %s', $bin, $route['duration_seconds'], $flags, escapeshellarg($route['url']), escapeshellarg($results->routePath($route['key'])));
            $steps[] = sprintf('php artisan benchmark:http-summary %s', escapeshellarg($route['key']));
        }

        return implode(' && ', $steps);
    }
}
