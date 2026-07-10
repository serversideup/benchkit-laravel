<?php

namespace App\Http\Controllers;

use App\Actions\Results\AssembleResultsDocument;
use Illuminate\Http\JsonResponse;

class ResultsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json((new AssembleResultsDocument)->execute());
    }
}
