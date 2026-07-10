<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Points benchmark.results_path at a throwaway temp directory so tests can
 * write result fixtures without touching the real results directory.
 * Laravel boots the trait automatically via setUp/tearDown name conventions.
 */
trait UsesFakeResultsPath
{
    protected string $resultsPath;

    protected function setUpUsesFakeResultsPath(): void
    {
        $this->resultsPath = sys_get_temp_dir().'/benchkit-results-'.uniqid();
        File::ensureDirectoryExists($this->resultsPath);
        config(['benchmark.results_path' => $this->resultsPath]);
    }

    protected function tearDownUsesFakeResultsPath(): void
    {
        File::deleteDirectory($this->resultsPath);
    }
}
