<?php

namespace App\Benchmarks\Php\Database;

use App\Benchmarks\Php\BaseBenchmark;
use Illuminate\Support\Facades\DB;
use PhpBench\Attributes as Bench;

/**
 * Benchmark comparing different query methods and optimizations.
 */
#[Bench\BeforeMethods('setUpDatabase')]
#[Bench\AfterMethods('tearDownDatabase')]
class QueryBenchmark extends BaseBenchmark
{
    private const TABLE_NAME = 'benchmark_products';

    private const RECORDS_COUNT = 10000;

    /**
     * Rows the headline read subject fetches, one query each. Matches the
     * record count the insert, update, and delete headlines operate on.
     */
    private const HEADLINE_READ_RECORDS = 100;

    /**
     * Set up the test database table with sample data.
     */
    public function setUpDatabase(): void
    {
        $this->setUp();

        $this->resetTestTable(self::TABLE_NAME, function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('price');
            $table->index('is_active');
            $table->index(['price', 'is_active']);
        });

        // Seed deterministic data so query row counts are identical across runs
        // and hosts. Ids are explicit so the read-by-id subject asks for rows
        // that exist without depending on where the sequence happens to start.
        $records = [];
        for ($i = 0; $i < self::RECORDS_COUNT; $i++) {
            $records[] = [
                'id' => $i + 1,
                'name' => "Product {$i}",
                'description' => "Description for product {$i}",
                'price' => ($i % 990) + 10,
                'stock' => $i % 500,
                'is_active' => $i % 2 === 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($records) >= 1000) {
                DB::table(self::TABLE_NAME)->insert($records);
                $records = [];
            }
        }

        if (! empty($records)) {
            DB::table(self::TABLE_NAME)->insert($records);
        }
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
     * The headline read: one SELECT per record, looked up by primary key.
     *
     * The four CRUD tiles share a bar scale, which only means something if
     * they measure the same unit of work. Insert, update, and delete each run
     * 100 statements against one row apiece; the read headline used to be
     * benchSelectWithLimit — a single SELECT returning 100 rows, so roughly a
     * hundredth of the work, reported next to them as though it were the same.
     * This runs the same shape they do, against the same `where id = ?`.
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'select'])]
    public function benchSelectIndividualById(): void
    {
        for ($id = 1; $id <= self::HEADLINE_READ_RECORDS; $id++) {
            DB::table(self::TABLE_NAME)
                ->where('id', $id)
                ->first();
        }
    }

    /**
     * Benchmark simple select query using DB facade.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'select'])]
    public function benchSimpleSelect(): void
    {
        DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->where('price', '>', 100)
            ->get();
    }

    /**
     * Benchmark select with limit.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'select'])]
    public function benchSelectWithLimit(): void
    {
        DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->where('price', '>', 100)
            ->limit(100)
            ->get();
    }

    /**
     * Benchmark select with specific columns (vs SELECT *).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'select-optimized'])]
    public function benchSelectSpecificColumns(): void
    {
        DB::table(self::TABLE_NAME)
            ->select('id', 'name', 'price')
            ->where('is_active', true)
            ->where('price', '>', 100)
            ->limit(100)
            ->get();
    }

    /**
     * Benchmark chunked query processing.
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'chunked'])]
    public function benchChunkedQuery(): void
    {
        DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->orderBy('id')
            ->chunk(1000, function ($products) {
                foreach ($products as $product) {
                    $name = $product->name;
                }
            });
    }

    /**
     * Benchmark lazy query (using cursor).
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'lazy'])]
    public function benchLazyQuery(): void
    {
        $cursor = DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->cursor();

        foreach ($cursor as $product) {
            $name = $product->name;
        }
    }

    /**
     * Benchmark aggregate query (COUNT).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'aggregate'])]
    public function benchCountQuery(): void
    {
        DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->where('price', '>', 100)
            ->count();
    }

    /**
     * Benchmark aggregate query (AVG, SUM).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'aggregate'])]
    public function benchAggregateQuery(): void
    {
        DB::table(self::TABLE_NAME)
            ->where('is_active', true)
            ->selectRaw('AVG(price) as avg_price, SUM(stock) as total_stock, COUNT(*) as count')
            ->first();
    }

    /**
     * Benchmark using raw SQL.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['database', 'query', 'raw'])]
    public function benchRawQuery(): void
    {
        DB::select(
            'SELECT id, name, price FROM '.self::TABLE_NAME.' WHERE is_active = ? AND price > ? LIMIT 100',
            [true, 100]
        );
    }
}
