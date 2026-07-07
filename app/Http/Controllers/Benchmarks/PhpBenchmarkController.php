<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\PhpBenchmarkResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Benchmarks\PhpBenchmarkRequest;
use App\Support\PhpBenchCommand;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PhpBenchmarkController extends Controller
{
    public function index(PhpBenchmarkRequest $request): Response
    {
        $command = (new PhpBenchCommand)->build($request->input('mode', 'full'));

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
