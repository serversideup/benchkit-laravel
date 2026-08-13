<?php

namespace App\Benchmarks\Php\Database;

use App\Benchmarks\Php\BaseBenchmark;
use Illuminate\Support\Facades\DB;
use PhpBench\Attributes as Bench;

/**
 * Benchmark comparing Eloquent ORM vs DB Facade for insert operations.
 *
 * Subjects insert only. Cleanup must stay out of a measured body because
 * phpbench times the whole method, and a table reset there is charged to the
 * insert — which distorted the bulk-vs-individual comparison most, pitting one
 * bulk INSERT against a four-statement reset. Measured at 15% of the bulk
 * figure, understating its advantage over individual inserts by a similar
 * margin.
 *
 * Hence one revolution per iteration: setUpDatabase() runs per iteration, so
 * every measurement is exactly RECORDS_COUNT inserts into an empty table. More
 * iterations replace the lost revolutions and hold relative stdev near 1%.
 *
 * And hence no warmup. phpbench runs before-methods once and then calls the
 * subject body for each warmup revolution, so warmup here left 100 rows per
 * revolution behind: the measured insert ran against a populated table and a
 * grown unique index, and quick mode (one warmup) and full (two) did not even
 * measure the same thing. BaseBenchmark::prime() does the warming instead,
 * untimed and against a throwaway table.
 */
#[Bench\BeforeMethods('setUpDatabase')]
#[Bench\AfterMethods('tearDownDatabase')]
class InsertBenchmark extends BaseBenchmark
{
    private const TABLE_NAME = 'benchmark_users';

    private const RECORDS_COUNT = 100;

    private const CHUNK_SIZE = 10;

    private int $emailSequence = 0;

    /**
     * Set up the test database table.
     */
    public function setUpDatabase(): void
    {
        $this->setUp();

        $this->resetTestTable(self::TABLE_NAME, function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Clean up the test database table.
     */
    public function tearDownDatabase(): void
    {
        $this->dropTestTable(self::TABLE_NAME);

        $this->tearDown();
    }

    /**
     * Build a deterministic, unique email for the given seed index. A plain
     * sequence keeps values reproducible across runs, unlike uniqid().
     */
    private function uniqueEmail(int $index): string
    {
        return "user{$index}_".($this->emailSequence++).'@example.com';
    }

    /**
     * Benchmark inserting records using Eloquent ORM (one by one).
     *
     * This simulates the common pattern of creating records individually
     * using Eloquent models, which triggers model events and validations.
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['database', 'insert', 'eloquent'])]
    public function benchEloquentInsertIndividual(): void
    {
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            BenchmarkEloquentModel::create([
                'name' => "User {$i}",
                'email' => $this->uniqueEmail($i),
                'password' => 'password',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    /**
     * Benchmark inserting records using DB facade (one by one).
     *
     * This uses the DB facade without Eloquent overhead.
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['database', 'insert', 'db-facade'])]
    public function benchDbFacadeInsertIndividual(): void
    {
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            DB::table(self::TABLE_NAME)->insert([
                'name' => "User {$i}",
                'email' => $this->uniqueEmail($i),
                'password' => 'password',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    /**
     * Benchmark bulk insert using DB facade.
     *
     * This demonstrates the most efficient way to insert multiple records
     * using a single INSERT statement with multiple value sets.
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['database', 'insert', 'bulk'])]
    public function benchDbFacadeBulkInsert(): void
    {
        $records = [];
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            $records[] = [
                'name' => "User {$i}",
                'email' => $this->uniqueEmail($i),
                'password' => 'password',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        DB::table(self::TABLE_NAME)->insert($records);
    }

    /**
     * Benchmark chunked bulk insert using DB facade.
     *
     * This demonstrates inserting records in chunks, which can be more
     * memory-efficient for very large datasets.
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['database', 'insert', 'chunked'])]
    public function benchDbFacadeChunkedInsert(): void
    {
        $records = [];

        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            $records[] = [
                'name' => "User {$i}",
                'email' => $this->uniqueEmail($i),
                'password' => 'password',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($records) >= self::CHUNK_SIZE) {
                DB::table(self::TABLE_NAME)->insert($records);
                $records = [];
            }
        }

        if (! empty($records)) {
            DB::table(self::TABLE_NAME)->insert($records);
        }
    }
}
