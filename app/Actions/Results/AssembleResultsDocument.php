<?php

namespace App\Actions\Results;

use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;

class AssembleResultsDocument
{
    /**
     * Merge environment specs and all benchmark outputs into a single
     * machine-readable document so results can be shared, diffed, and
     * aggregated by the community.
     *
     * @return array{
     *     schema_version: int,
     *     generated_at: string,
     *     environment: array{server: array, php: array, laravel: ?array, php_variation: ?string, build_version: ?string},
     *     benchmarks: array{yabs: ?array, cfspeedtest: ?array, http: ?array, php: ?array}
     * }
     */
    public function execute(): array
    {
        return [
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
                'http' => (new HttpBenchmarkResults)->execute(),
                'php' => (new PhpBenchmarkResults)->execute(),
            ],
        ];
    }

    protected function buildVersion(): ?string
    {
        $path = base_path('.build-version');

        return file_exists($path) ? trim(file_get_contents($path)) : null;
    }
}
