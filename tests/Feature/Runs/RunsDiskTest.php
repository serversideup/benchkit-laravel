<?php

namespace Tests\Feature\Runs;

use Tests\TestCase;

class RunsDiskTest extends TestCase
{
    /**
     * The documented deployment mounts a named volume at the runs disk root.
     * Docker only inherits ownership from the image when that path already
     * exists there, so a directory missing from the repository becomes a
     * root-owned mountpoint that the app user cannot write a snapshot into.
     */
    public function test_the_runs_disk_root_ships_with_the_repository(): void
    {
        $root = config('filesystems.disks.runs.root');

        $this->assertDirectoryExists($root);
        $this->assertFileExists($root.'/.gitignore');
    }
}
