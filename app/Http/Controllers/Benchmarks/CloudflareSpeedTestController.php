<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\CloudflareSpeedTestResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Benchmarks\CloudflareSpeedTestRequest;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CloudflareSpeedTestController extends Controller
{
    public function index(CloudflareSpeedTestRequest $request): Response
    {
        $options = $request->input('network_test_type', 'ipv4') === 'ipv6' ? ' --ipv6' : ' --ipv4';

        $command = sprintf('%s%s', base_path('vendor/bin/cfspeedtest'), $options);

        return (new StreamedProcess($command))
            ->collectOutputTo((new CloudflareSpeedTestResults)->path())
            ->response();
    }

    public function results(): JsonResponse
    {
        $results = (new CloudflareSpeedTestResults)->execute();

        if ($results === null) {
            return response()->json(['status' => 'no_results'], 404);
        }

        return response()->json([
            'cfspeedtest_results' => $results,
        ]);
    }
}
