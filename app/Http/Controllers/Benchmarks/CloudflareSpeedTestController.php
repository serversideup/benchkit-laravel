<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\CloudflareSpeedTestResults;
use App\Http\Requests\Benchmarks\CloudflareSpeedTestRequest;
use App\Support\CfSpeedTestCommand;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CloudflareSpeedTestController extends StageController
{
    public function index(CloudflareSpeedTestRequest $request): Response
    {
        $command = (new CfSpeedTestCommand)->build($request->validated('network_test_type') ?? 'ipv4');

        return (new StreamedProcess($command))
            ->collectOutputTo((new CloudflareSpeedTestResults)->path())
            ->response();
    }

    public function results(): JsonResponse
    {
        return $this->resultsResponse((new CloudflareSpeedTestResults)->execute(), 'cfspeedtest_results');
    }
}
