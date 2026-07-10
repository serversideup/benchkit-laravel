<?php

namespace App\Http\Controllers\Benchmarks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class StageController extends Controller
{
    /**
     * Benchmark results as JSON, or 404 while the stage has not produced
     * results yet.
     *
     * @param  array<string, mixed>|null  $results
     */
    protected function resultsResponse(?array $results, ?string $key = null): JsonResponse
    {
        if ($results === null) {
            return response()->json(['status' => 'no_results'], 404);
        }

        return response()->json($key === null ? $results : [$key => $results]);
    }
}
