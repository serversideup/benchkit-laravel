<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\PhpBenchmarkResults;
use Illuminate\Http\JsonResponse;

class PhpBenchmarkController extends StageController
{
    public function results(): JsonResponse
    {
        $results = (new PhpBenchmarkResults)->execute();

        return $this->resultsResponse($results['headline'] ?? null, 'phpbench_results');
    }
}
