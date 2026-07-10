<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\PhpBenchmarkResults;
use App\Http\Requests\Benchmarks\PhpBenchmarkRequest;
use App\Support\PhpBenchCommand;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PhpBenchmarkController extends StageController
{
    public function index(PhpBenchmarkRequest $request): Response
    {
        $command = (new PhpBenchCommand)->build($request->validated('mode') ?? 'full');

        return (new StreamedProcess($command))->response();
    }

    public function results(): JsonResponse
    {
        $results = (new PhpBenchmarkResults)->execute();

        return $this->resultsResponse($results['headline'] ?? null, 'phpbench_results');
    }
}
