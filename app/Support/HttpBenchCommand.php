<?php

namespace App\Support;

use App\Actions\Results\HttpBenchmarkResults;

/**
 * Builds the oha command chain for the HTTP self-test: each route is load
 * tested in sequence, written to its own JSON file, and summarised for the
 * live console before the next route starts.
 */
class HttpBenchCommand
{
    /**
     * Seconds of throwaway load before each route's measured window, so a
     * cold OPcache/JIT, an unprimed route/config cache, and a just-booted
     * worker do not land in the numbers. Discarded — never parsed.
     */
    protected const WARMUP_SECONDS = 3;

    /**
     * @param  array{url: string, mode: string}  $target
     */
    public function build(array $target, int $duration, int $connections, int $ioMs): string
    {
        $bin = base_path('vendor/bin/oha');
        $results = new HttpBenchmarkResults;

        $steps = [];
        $position = 0;
        $total = count(HttpBenchmarkResults::ROUTES);

        foreach (HttpBenchmarkResults::ROUTES as $key => $path) {
            $position++;

            // The /bench/io route sleeps ?ms to model one outbound dependency
            // call; every other route measures the framework path as-is.
            $url = $target['url'].$path.($key === 'io' ? '?ms='.$ioMs : '');
            $insecure = str_starts_with($url, 'https://') ? ' --insecure' : '';
            $flags = sprintf('-c %d --no-tui --redirect 0%s --output-format json', $connections, $insecure);

            $steps[] = sprintf(
                'echo %s',
                escapeshellarg("Load testing {$path} ({$position} of {$total}) — {$duration}s at {$connections} connections against {$target['url']} [{$target['mode']}]")
            );
            // Warm up (discarded), then measure into the route's JSON file.
            $steps[] = sprintf('%s -z %ds %s %s > /dev/null 2>&1', $bin, self::WARMUP_SECONDS, $flags, escapeshellarg($url));
            $steps[] = sprintf('%s -z %ds %s %s > %s', $bin, $duration, $flags, escapeshellarg($url), escapeshellarg($results->routePath($key)));
            $steps[] = sprintf('php artisan benchmark:http-summary %s', escapeshellarg($key));
        }

        return implode(' && ', $steps);
    }
}
