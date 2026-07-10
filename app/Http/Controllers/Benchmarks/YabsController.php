<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\YabsResults;
use App\Http\Requests\Benchmarks\YabsBenchmarkRequest;
use App\Support\StreamedProcess;
use App\Support\YabsCommand;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class YabsController extends StageController
{
    public function index(YabsBenchmarkRequest $request): Response
    {
        $command = (new YabsCommand)->build($request->validated());

        return (new StreamedProcess($command))->response();
    }

    public function results(): JsonResponse
    {
        return $this->resultsResponse((new YabsResults)->execute());
    }
}
