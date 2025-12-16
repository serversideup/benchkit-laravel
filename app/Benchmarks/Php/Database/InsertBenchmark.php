<?php

namespace App\Benchmarks\Php\Database;

use App\Benchmarks\Php\BaseBenchmark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpBench\Attributes as Bench;
use App\Benchmarks\Php\Database\BenchmarkEloquentModel;

/**
 * Benchmark comparing Eloquent ORM vs DB Facade for insert operations.
 * 
 * This benchmark demonstrates the performance difference between using
 * Laravel's Eloquent ORM and the DB facade for bulk insert operations.
 */
#[Bench\BeforeMethods('setUpDatabase')]
#[Bench\AfterMethods('tearDownDatabase')]
class InsertBenchmark extends BaseBenchmark
{
    private const TABLE_NAME = 'benchmark_users';
    private const RECORDS_COUNT = 100;
    private const CHUNK_SIZE = 10;

    /**
     * Set up the test database table.
     */
    public function setUpDatabase(): void
    {
        $this->ensureTestTable(self::TABLE_NAME, function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $this->truncateTable(self::TABLE_NAME);
    }

    /**
     * Clean up the test database table.
     */
    public function tearDownDatabase(): void
    {
        $this->truncateTable(self::TABLE_NAME);
    }

    /**
     * Benchmark inserting records using Eloquent ORM (one by one).
     * 
     * This simulates the common pattern of creating records individually
     * using Eloquent models, which triggers model events and validations.
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'insert', 'eloquent'])]
    public function benchEloquentInsertIndividual(): void
    {
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            BenchmarkEloquentModel::create([
                'name' => "User {$i}",
                'email' => "user{$i}_" . uniqid() . "@example.com",
                'password' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->truncateTable(self::TABLE_NAME);
    }

    /**
     * Benchmark inserting records using DB facade (one by one).
     * 
     * This uses the DB facade without Eloquent overhead.
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'insert', 'db-facade'])]
    public function benchDbFacadeInsertIndividual(): void
    {
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            DB::table(self::TABLE_NAME)->insert([
                'name' => "User {$i}",
                'email' => "user{$i}_" . uniqid() . "@example.com",
                'password' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->truncateTable(self::TABLE_NAME);
    }

    /**
     * Benchmark bulk insert using DB facade.
     * 
     * This demonstrates the most efficient way to insert multiple records
     * using a single INSERT statement with multiple value sets.
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'insert', 'bulk'])]
    public function benchDbFacadeBulkInsert(): void
    {
        $records = [];
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            $records[] = [
                'name' => "User {$i}",
                'email' => "user{$i}_" . uniqid() . "@example.com",
                'password' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table(self::TABLE_NAME)->insert($records);
        $this->truncateTable(self::TABLE_NAME);
    }

    /**
     * Benchmark chunked bulk insert using DB facade.
     * 
     * This demonstrates inserting records in chunks, which can be more
     * memory-efficient for very large datasets.
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'insert', 'chunked'])]
    public function benchDbFacadeChunkedInsert(): void
    {
        $records = [];

        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            $records[] = [
                'name' => "User {$i}",
                'email' => "user{$i}_" . uniqid() . "@example.com",
                'password' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= self::CHUNK_SIZE) {
                DB::table(self::TABLE_NAME)->insert($records);
                $records = [];
            }
        }

        // Insert remaining records
        if (!empty($records)) {
            DB::table(self::TABLE_NAME)->insert($records);
        }

        $this->truncateTable(self::TABLE_NAME);
    }
}