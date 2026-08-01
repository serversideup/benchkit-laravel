<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\YabsResults;
use Illuminate\Http\JsonResponse;

class YabsController extends StageController
{
    public function results(): JsonResponse
    {
        return $this->resultsResponse((new YabsResults)->execute());
    }
}
