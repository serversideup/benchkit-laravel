<?php

namespace App\Benchmarks\Php;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseBenchmark
{
    /**
     * Set up the benchmark environment.
     * This method runs before each benchmark iteration.
     */
    public function setUp(): void
    {
        // Disable query logging for accurate benchmarks
        DB::connection()->disableQueryLog();
        
        // Clear any query caches
        DB::connection()->flushQueryLog();
    }

    /**
     * Clean up after benchmark.
     * This method runs after each benchmark iteration.
     */
    public function tearDown(): void
    {
        // Re-enable query logging if needed
        DB::connection()->enableQueryLog();
    }

    /**
     * Truncate a table safely.
     */
    protected function truncateTable(string $table): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table($table)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support TRUNCATE, but DELETE works
            // and foreign key constraints are handled differently
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::table($table)->delete();
            DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            // For other databases, try simple truncate
            DB::table($table)->truncate();
        }
    }

    /**
     * Create a test table if it doesn't exist.
     */
    protected function ensureTestTable(string $table, callable $schemaBuilder): void
    {
        if (!Schema::hasTable($table)) {
            Schema::create($table, $schemaBuilder);
        }
    }

    /**
     * Drop a test table if it exists.
     */
    protected function dropTestTable(string $table): void
    {
        Schema::dropIfExists($table);
    }
}