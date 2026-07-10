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
     * @param  array{url: string, mode: string}  $target
     */
    public function build(array $target, int $duration, int $connections): string
    {
        $bin = base_path('vendor/bin/oha');
        $results = new HttpBenchmarkResults;

        $steps = [];
        $position = 0;
        $total = count(HttpBenchmarkResults::ROUTES);

        foreach (HttpBenchmarkResults::ROUTES as $key => $path) {
            $position++;
            $url = $target['url'].$path;
            $insecure = str_starts_with($url, 'https://') ? ' --insecure' : '';

            $steps[] = sprintf(
                'echo %s',
                escapeshellarg("Load testing {$path} ({$position} of {$total}) — {$duration}s at {$connections} connections against {$target['url']} [{$target['mode']}]")
            );
            $steps[] = sprintf(
                '%s -z %ds -c %d --no-tui --redirect 0%s --output-format json %s > %s',
                $bin,
                $duration,
                $connections,
                $insecure,
                escapeshellarg($url),
                escapeshellarg($results->routePath($key))
            );
            $steps[] = sprintf('php artisan benchmark:http-summary %s', escapeshellarg($key));
        }

        return implode(' && ', $steps);
    }
}
