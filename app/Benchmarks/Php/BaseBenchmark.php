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
     * A scratch table used only to warm the query paths. Named distinctly so it
     * can never collide with a fixture a subject measures against.
     */
    private const PRIME_TABLE = 'benchmark_prime';

    /**
     * Runs per iteration, outside the measured body. Anything a subject needs
     * set up or torn down belongs here rather than in the subject itself.
     */
    public function setUp(): void
    {
        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();

        $this->now = now()->format(DB::connection()->getQueryGrammar()->getDateFormat());

        $this->prime();
    }

    public function tearDown(): void
    {
        DB::connection()->enableQueryLog();
    }

    /**
     * Warm the query paths a measured body will use, against a throwaway table.
     *
     * The CRUD subjects run one revolution per iteration, so the measurement is
     * the *first* time that code path executes in the process. Without this it
     * would also be paying for one-time work — autoloading the query builder
     * and grammar, preparing the first statement on the connection — and the
     * operation that happened to touch a class first would be charged for
     * loading it.
     *
     * phpbench's own warmup revolutions cannot do this job: they run after the
     * before-methods and call the subject body itself, so on a destructive
     * subject they consume the fixture the measurement was supposed to see. A
     * warmed-up delete measured 100 DELETEs against rows its warmup had already
     * removed. Priming here keeps the warming and drops the side effect.
     */
    protected function prime(): void
    {
        Schema::dropIfExists(self::PRIME_TABLE);

        Schema::create(self::PRIME_TABLE, function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('stock');
            $table->timestamps();
        });

        DB::table(self::PRIME_TABLE)->insert([
            'id' => 1,
            'name' => 'prime',
            'stock' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table(self::PRIME_TABLE)->where('id', 1)->first();
        DB::table(self::PRIME_TABLE)->where('id', 1)->update(['stock' => 2]);
        DB::table(self::PRIME_TABLE)->where('id', 1)->delete();

        Schema::dropIfExists(self::PRIME_TABLE);
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
