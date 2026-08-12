<?php

namespace App\Actions\Results;

use App\Actions\Specs\DatabaseSpecs;
use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;

class AssembleResultsDocument
{
    /**
     * The shape of a run document, and — more to the point — what its numbers
     * mean. It is bumped whenever a measurement changes such that old runs
     * cannot sit in the same gallery as new ones, which is why the community
     * validator rejects superseded versions instead of warning about them.
     *
     * 1 → 2: CRUD subjects were rebuilding their own state inside the timed
     *        body, so delete reported ~2.4x its real cost.
     * 2 → 3: create and update timed PHP datetime work alongside the query,
     *        which read and delete did not, so the four were not comparable;
     *        and read measured one SELECT of 100 rows against the others'
     *        100 statements. Runs now carry the spread behind each mean and
     *        the database's durability settings.
     */
    public const SCHEMA_VERSION = 3;

    /**
     * Merge environment specs and all benchmark outputs into a single
     * machine-readable document so results can be shared, diffed, and
     * aggregated by the community.
     *
     * @return array{
     *     schema_version: int,
     *     generated_at: string,
     *     environment: array{server: array, php: array, laravel: array, database: array, php_variation: ?string, build_version: ?string},
     *     benchmarks: array{yabs: ?array, cfspeedtest: ?array, http: ?array, php: ?array}
     * }
     */
    public function execute(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'environment' => [
                'server' => (new ServerSpecs)->execute(),
                'php' => (new PhpSpecs)->execute(),
                'laravel' => (new LaravelSpecs)->execute(),
                'database' => (new DatabaseSpecs)->execute(),
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

    /**
     * The Docker build writes the image tag to .build-version (see the
     * Dockerfile); the file does not exist on non-Docker installs.
     */
    protected function buildVersion(): ?string
    {
        $path = base_path('.build-version');

        return file_exists($path) ? trim(file_get_contents($path)) : null;
    }
}
