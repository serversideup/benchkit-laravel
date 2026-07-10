<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Http\Requests\Benchmarks\HttpBenchmarkRequest;
use App\Support\BenchmarkHttpItems;
use App\Support\HttpBenchCommand;
use App\Support\HttpBenchmarkTarget;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HttpBenchmarkController extends StageController
{
    public function index(HttpBenchmarkRequest $request): Response
    {
        $target = (new HttpBenchmarkTarget)->resolve();

        if ($target === null) {
            return response()->json([
                'status' => 'unreachable',
                'message' => 'The application could not reach itself over HTTP. Set BENCHMARK_HTTP_URL to a URL this server can reach.',
            ], 503);
        }

        $duration = (int) ($request->validated('duration') ?? config('benchmark.http.duration_seconds'));
        $connections = (int) ($request->validated('connections') ?? config('benchmark.http.connections'));

        BenchmarkHttpItems::ensure();

        (new HttpBenchmarkResults)->writeMeta($target, $duration, $connections);

        $command = (new HttpBenchCommand)->build($target, $duration, $connections);

        return (new StreamedProcess($command))->response();
    }

    public function results(): JsonResponse
    {
        return $this->resultsResponse((new HttpBenchmarkResults)->execute(), 'http_results');
    }
}
