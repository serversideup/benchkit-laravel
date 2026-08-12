<?php

namespace App\Benchmarks\Php;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseBenchmark
{
    /**
     * The one rule every subject in this suite follows: a measured body
     * contains the operation being measured and nothing else. phpbench times
     * the whole method, so anything else in there is charged to the result.
     *
     * Setup, teardown, seeding, and any value the body needs precomputed
     * belong in setUp() — it runs per iteration, outside the measurement.
     */

    /**
     * A timestamp resolved once per iteration, for subjects that write
     * `created_at`/`updated_at`.
     *
     * Bodies bind this string instead of calling now(). A Carbon instance
     * passed as a binding is re-formatted by the query grammar on every
     * statement, and that datetime work was landing inside the measurement:
     * roughly half of the reported insert cost on a healthy host, and far more
     * than that on hosts where PHP date handling is slow. It made Create and
     * Update measure PHP while Read and Delete, which touch no timestamps,
     * measured the database — so the four CRUD headlines were not comparable
     * with each other or across hosts.
     */
    protected string $now;

    /**
     * Runs per iteration, outside the measured body. Anything a subject needs
     * set up or torn down belongs here rather than in the subject itself.
     */
    public function setUp(): void
    {
        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();

        $this->now = now()->format(DB::connection()->getQueryGrammar()->getDateFormat());
    }

    public function tearDown(): void
    {
        DB::connection()->enableQueryLog();
    }

    /**
     * Start an iteration from a table that is guaranteed empty and guaranteed
     * to have this benchmark's own schema.
     *
     * Several classes share the `benchmark_products` name with different
     * columns, so "create it if it's missing" left whichever run came first in
     * charge of the schema — and an interrupted run left a populated table
     * behind for the next one to measure against. Dropping first costs nothing
     * that gets measured and removes both.
     */
    protected function resetTestTable(string $table, callable $schemaBuilder): void
    {
        Schema::dropIfExists($table);
        Schema::create($table, $schemaBuilder);
    }

    protected function dropTestTable(string $table): void
    {
        Schema::dropIfExists($table);
    }
}
