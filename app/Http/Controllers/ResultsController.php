<?php

namespace App\Http\Controllers;

use App\Actions\Results\CloudflareSpeedTestResults;
use App\Actions\Results\PhpBenchmarkResults;
use App\Actions\Results\YabsResults;
use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;
use Illuminate\Http\JsonResponse;

class ResultsController extends Controller
{
    /**
     * Merge environment specs and all benchmark outputs into a single
     * machine-readable document so results can be shared, diffed, and
     * aggregated by the community.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'environment' => [
                'server' => (new ServerSpecs)->execute(),
                'php' => (new PhpSpecs)->execute(),
                'laravel' => json_decode((new LaravelSpecs)->execute(), true),
                'php_variation' => config('benchmark.php_variation'),
                'build_version' => $this->buildVersion(),
            ],
            'benchmarks' => [
                'yabs' => (new YabsResults)->execute(),
                'cfspeedtest' => (new CloudflareSpeedTestResults)->execute(),
                'php' => (new PhpBenchmarkResults)->execute(),
            ],
        ]);
    }

    protected function buildVersion(): ?string
    {
        $path = base_path('.build-version');

        return file_exists($path) ? trim(file_get_contents($path)) : null;
    }
}
