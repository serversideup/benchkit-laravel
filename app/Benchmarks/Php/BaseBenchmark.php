<?php

namespace App\Benchmarks\Php;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseBenchmark
{
    /**
     * Runs per iteration, outside the measured body. Anything a subject needs
     * set up or torn down belongs here rather than in the subject itself.
     */
    public function setUp(): void
    {
        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();
    }

    public function tearDown(): void
    {
        DB::connection()->enableQueryLog();
    }

    protected function truncateTable(string $table): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table($table)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            // SQLite has no TRUNCATE; DELETE plus resetting the sequence is the
            // equivalent, and foreign keys have to come off around it.
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::table($table)->delete();
            DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::table($table)->truncate();
        }
    }

    protected function ensureTestTable(string $table, callable $schemaBuilder): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $schemaBuilder);
        }
    }

    protected function dropTestTable(string $table): void
    {
        Schema::dropIfExists($table);
    }
}
