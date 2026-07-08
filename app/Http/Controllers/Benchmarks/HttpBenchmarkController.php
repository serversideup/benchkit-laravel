<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Http\Controllers\Controller;
use App\Support\BenchmarkHttpItems;
use App\Support\HttpBenchmarkTarget;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class HttpBenchmarkController extends Controller
{
    public function index(): Response
    {
        $target = (new HttpBenchmarkTarget)->resolve();

        if ($target === null) {
            return response()->json([
                'status' => 'unreachable',
                'message' => 'The application could not reach itself over HTTP. Set BENCHMARK_HTTP_URL to a URL this server can reach.',
            ], 503);
        }

        BenchmarkHttpItems::ensure();

        $this->writeMeta($target);

        return (new StreamedProcess($this->buildCommand($target)))->response();
    }

    public function results(): JsonResponse
    {
        $results = (new HttpBenchmarkResults)->execute();

        if ($results === null) {
            return response()->json(['status' => 'no_results'], 404);
        }

        return response()->json([
            'http_results' => $results,
        ]);
    }

    /**
     * @param  array{url: string, mode: string}  $target
     */
    protected function buildCommand(array $target): string
    {
        $bin = base_path('vendor/bin/oha');
        $duration = (int) config('benchmark.http.duration_seconds');
        $connections = (int) config('benchmark.http.connections');
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

    /**
     * @param  array{url: string, mode: string}  $target
     */
    protected function writeMeta(array $target): void
    {
        $results = new HttpBenchmarkResults;

        File::ensureDirectoryExists(dirname($results->metaPath()));
        File::put($results->metaPath(), json_encode([
            'target' => $target['url'],
            'mode' => $target['mode'],
            'duration_seconds' => (int) config('benchmark.http.duration_seconds'),
            'connections' => (int) config('benchmark.http.connections'),
        ]));
    }
}
