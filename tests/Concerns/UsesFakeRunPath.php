<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Points benchmark.run_path at a throwaway temp directory so tests can
 * write run state without disturbing a real run in progress on the
 * developer's machine. Laravel boots the trait automatically via
 * setUp/tearDown name conventions.
 */
trait UsesFakeRunPath
{
    protected string $runPath;

    protected function setUpUsesFakeRunPath(): void
    {
        $this->runPath = sys_get_temp_dir().'/benchkit-run-'.uniqid();
        File::ensureDirectoryExists($this->runPath);
        config(['benchmark.run_path' => $this->runPath]);
    }

    protected function tearDownUsesFakeRunPath(): void
    {
        File::deleteDirectory($this->runPath);
    }
}
