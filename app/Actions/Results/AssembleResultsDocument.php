<?php

namespace App\Actions\Results;

use App\Actions\Specs\DatabaseSpecs;
use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;
use App\Actions\Specs\WebRuntimeSpecs;

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
     * 3 → 4: warmup revolutions ran the subject body without rebuilding the
     *        fixture, so delete measured 100 DELETEs matching no rows and
     *        reported roughly half its real cost. Iteration spread was also
     *        being filtered by a 3% retry gate before publication, so the
     *        stated variance was narrower than the measured one. The PHP
     *        environment now describes the process that served the load test
     *        rather than the CLI process that assembled the document.
     */
    public const SCHEMA_VERSION = 4;

    /**
     * Merge environment specs and all benchmark outputs into a single
     * machine-readable document so results can be shared, diffed, and
     * aggregated by the community.
     *
     * @return array{
     *     schema_version: int,
     *     generated_at: string,
     *     environment: array{server: array, php: array, php_environment_source: string, laravel: array, database: array, php_variation: ?string, build_version: ?string},
     *     benchmarks: array{yabs: ?array, cfspeedtest: ?array, http: ?array, php: ?array}
     * }
     */
    public function execute(): array
    {
        $webRuntime = (new WebRuntimeSpecs)->read();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'environment' => [
                'server' => (new ServerSpecs)->execute(),
                'php' => $webRuntime ?? (new PhpSpecs)->publicSnapshot(),
                // Which process the block above describes. This document is
                // assembled by the CLI, so without the HTTP stage having asked
                // the web server for its own answer, "php" is the CLI's — a
                // different SAPI with a different opcache, memory limit, and
                // ini. Saying which one it is beats implying it is the server.
                'php_environment_source' => $webRuntime === null ? 'cli' : 'web',
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
