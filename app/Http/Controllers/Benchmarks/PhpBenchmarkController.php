<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\PhpBenchmarkResults;
use App\Http\Controllers\Controller;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PhpBenchmarkController extends Controller
{
    public function index(): Response
    {
        $command = sprintf(
            '%s run --report=comparison --output=csv > %s',
            base_path('vendor/bin/phpbench'),
            escapeshellarg((new PhpBenchmarkResults)->path())
        );

        return (new StreamedProcess($command))->response();
    }

    public function results(): JsonResponse
    {
        $results = (new PhpBenchmarkResults)->execute();

        if ($results === null) {
            return response()->json(['status' => 'no_results'], 404);
        }

        return response()->json([
            'phpbench_results' => $results['headline'],
        ]);
    }
}
