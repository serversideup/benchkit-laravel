<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\YabsResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Benchmarks\YabsBenchmarkRequest;
use App\Support\StreamedProcess;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class YabsController extends Controller
{
    public function index(YabsBenchmarkRequest $request): Response
    {
        $command = sprintf(
            '%s%s %s',
            base_path('vendor/bin/yabs'),
            $this->buildOptions($request),
            (new YabsResults)->path()
        );

        return (new StreamedProcess($command))->response();
    }

    public function results(): JsonResponse
    {
        $results = (new YabsResults)->execute();

        if ($results === null) {
            return response()->json(['status' => 'no_results'], 404);
        }

        return response()->json($results);
    }

    protected function buildOptions(YabsBenchmarkRequest $request): string
    {
        $options = '';

        if (! $request->boolean('disk')) {
            $options .= ' -f';
        }

        if (! $request->boolean('geekbench')) {
            $options .= ' -g';
        } else {
            $options .= match ($request->integer('geekbench_version', 6)) {
                4 => ' -4',
                5 => ' -5',
                default => ' -6',
            };
        }

        if (! $request->boolean('iperf')) {
            $options .= ' -i';
        }

        return $options.' -w';
    }
}
