<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\CloudflareSpeedTestResults;
use Illuminate\Http\JsonResponse;

class CloudflareSpeedTestController extends StageController
{
    public function results(): JsonResponse
    {
        return $this->resultsResponse((new CloudflareSpeedTestResults)->execute(), 'cfspeedtest_results');
    }
}
