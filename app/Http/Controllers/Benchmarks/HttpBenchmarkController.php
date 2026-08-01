<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use Illuminate\Http\JsonResponse;

class HttpBenchmarkController extends StageController
{
    public function results(): JsonResponse
    {
        return $this->resultsResponse((new HttpBenchmarkResults)->execute(), 'http_results');
    }
}
