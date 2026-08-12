<?php

namespace Tests\Feature\Specs;

use App\Actions\Specs\DatabaseSpecs;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSpecsTest extends TestCase
{
    public function test_it_reports_the_driver_and_version_of_the_connection_the_benchmarks_use(): void
    {
        $specs = (new DatabaseSpecs)->execute();

        $this->assertSame('sqlite', $specs['driver']);
        $this->assertNotNull($specs['version']);
    }

    /**
     * These are the settings that decide whether a commit waits for the disk,
     * which is the single largest influence on the CRUD write numbers. A run
     * without them can't be told apart from a run on a slow disk.
     */
    public function test_it_reports_the_durability_settings_that_dominate_write_benchmarks(): void
    {
        $specs = (new DatabaseSpecs)->execute();

        $this->assertArrayHasKey('journal_mode', $specs['durability']);
        $this->assertArrayHasKey('synchronous', $specs['durability']);
        $this->assertContains($specs['durability']['synchronous'], ['off', 'normal', 'full', 'extra']);
    }

    /**
     * A database on tmpfs is measuring RAM. That belongs on the run; the path
     * it lives at identifies the machine and does not.
     */
    public function test_it_reports_where_the_database_lives_without_publishing_the_path(): void
    {
        $specs = (new DatabaseSpecs)->execute();

        $this->assertSame('memory', $specs['filesystem']);
        $this->assertStringNotContainsString('/', json_encode($specs));
    }

    /**
     * Silence is not an answer. "off" is the setting a fast write result would
     * be blamed on, so an unreachable database must report nothing rather than
     * let a missing value cast itself to level 0.
     */
    public function test_it_reports_nothing_rather_than_failing_a_run_when_the_database_is_unreachable(): void
    {
        config()->set('database.connections.broken', [
            'driver' => 'sqlite',
            'database' => '/nonexistent/directory/benchkit.sqlite',
        ]);
        config()->set('database.default', 'broken');
        DB::purge('broken');

        $specs = (new DatabaseSpecs)->execute();

        $this->assertNull($specs['version']);
        $this->assertNull($specs['filesystem']);
        $this->assertNull($specs['durability']['synchronous']);
        $this->assertNull($specs['durability']['journal_mode']);
    }

    public function test_it_reports_no_durability_settings_for_a_driver_it_has_no_names_for(): void
    {
        config()->set('database.connections.exotic', ['driver' => 'sqlsrv', 'database' => 'benchkit']);
        config()->set('database.default', 'exotic');
        DB::purge('exotic');

        $this->assertSame([], (new DatabaseSpecs)->execute()['durability']);
    }
}
