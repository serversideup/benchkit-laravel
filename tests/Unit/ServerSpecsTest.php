<?php

namespace Tests\Unit;

use App\Actions\Specs\ServerSpecs;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * /proc describes the host, not the container. The core count seeds the load
 * sweep and the worker suggestion, so a container limited to two cores on a
 * sixty-four core host has to report two.
 */
class ServerSpecsTest extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/benchkit-specs-'.uniqid();
        mkdir("{$this->root}/proc", 0777, true);
        mkdir("{$this->root}/cgroup", 0777, true);

        file_put_contents("{$this->root}/proc/cpuinfo", str_repeat("processor\t: 0\nmodel name\t: Test CPU\ncpu MHz\t\t: 2000.000\n\n", 8));
        file_put_contents("{$this->root}/proc/meminfo", "MemTotal:       16777216 kB\nMemFree:        1000000 kB\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    protected function specs(): array
    {
        return (new ServerSpecs("{$this->root}/proc", "{$this->root}/cgroup"))->execute();
    }

    public function test_an_unlimited_container_reports_the_host_figures(): void
    {
        file_put_contents("{$this->root}/cgroup/cpu.max", "max 100000\n");
        file_put_contents("{$this->root}/cgroup/memory.max", "max\n");

        $specs = $this->specs();

        $this->assertSame('8', $specs['cpu_cores']);
        $this->assertSame('16384 MB', $specs['ram']);
    }

    public function test_a_cgroup_v2_quota_caps_the_core_count_and_memory(): void
    {
        file_put_contents("{$this->root}/cgroup/cpu.max", "150000 100000\n");
        file_put_contents("{$this->root}/cgroup/memory.max", (string) (2 * 1024 ** 3));

        $specs = $this->specs();

        $this->assertSame('2', $specs['cpu_cores'], 'A 1.5 CPU quota is two cores worth of scheduling.');
        $this->assertSame('2048 MB', $specs['ram']);
    }

    public function test_a_cgroup_v1_quota_is_read_the_same_way(): void
    {
        mkdir("{$this->root}/cgroup/cpu");
        mkdir("{$this->root}/cgroup/memory");
        file_put_contents("{$this->root}/cgroup/cpu/cpu.cfs_quota_us", "400000\n");
        file_put_contents("{$this->root}/cgroup/cpu/cpu.cfs_period_us", "100000\n");
        file_put_contents("{$this->root}/cgroup/memory/memory.limit_in_bytes", "9223372036854771712\n");

        $specs = $this->specs();

        $this->assertSame('4', $specs['cpu_cores']);
        $this->assertSame('16384 MB', $specs['ram'], 'A v1 "unlimited" value is larger than the host, so the host wins.');
    }

    public function test_a_quota_above_the_host_never_raises_the_count(): void
    {
        file_put_contents("{$this->root}/cgroup/cpu.max", "3200000 100000\n");

        $this->assertSame('8', $this->specs()['cpu_cores']);
    }

    public function test_unreadable_files_degrade_to_empty_rather_than_zero(): void
    {
        unlink("{$this->root}/proc/meminfo");

        $this->assertSame('', $this->specs()['ram']);
    }
}
