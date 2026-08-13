<?php

namespace App\Benchmarks\Php\Database;

use App\Benchmarks\Php\BaseBenchmark;
use Illuminate\Support\Facades\DB;
use PhpBench\Attributes as Bench;

/**
 * Update Benchmark
 *
 * Compares different update strategies:
 * - Individual vs bulk updates
 * - Eloquent vs Query Builder
 * - Conditional updates
 * - Update with increments
 *
 * One revolution per iteration so every subject sees the dataset setUp() seeded.
 * Several of these mutate the rows they select on — benchConditionalUpdate
 * flips is_active, benchIncrementUpdate moves stock — so under repeated
 * revolutions the second measurement onward ran against data the first had
 * already changed.
 *
 * Warmup revolutions did the same damage for the same reason: phpbench calls
 * the subject body to warm up, without re-running the before-methods, so the
 * measurement still saw mutated data. BaseBenchmark::prime() warms the query
 * paths untimed against a throwaway table instead.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
class UpdateBenchmark extends BaseBenchmark
{
    private array $recordIds = [];

    private int $totalRecords = 1000;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetTestTable('benchmark_products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        // Seed with deterministic test data so every run operates on identical rows
        $records = [];
        for ($i = 0; $i < $this->totalRecords; $i++) {
            $records[] = [
                'name' => "Product {$i}",
                'price' => ($i % 990) + 10,
                'stock' => $i % 100,
                'is_active' => true,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        DB::table('benchmark_products')->insert($records);
        $this->recordIds = DB::table('benchmark_products')->pluck('id')->toArray();
    }

    public function tearDown(): void
    {
        $this->dropTestTable('benchmark_products');
        parent::tearDown();
    }

    /**
     * Update records individually using Query Builder
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database'])]
    public function benchQueryBuilderIndividual(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        foreach ($idsToUpdate as $id) {
            DB::table('benchmark_products')
                ->where('id', $id)
                ->update([
                    'price' => (($id * 7) % 990) + 10,
                    'updated_at' => $this->now,
                ]);
        }
    }

    /**
     * Update records individually using Eloquent
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'eloquent'])]
    public function benchEloquentIndividual(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        foreach ($idsToUpdate as $id) {
            $model = BenchmarkProductModel::find($id);

            if ($model) {
                $model->update([
                    'price' => (($id * 7) % 990) + 10,
                    'updated_at' => $this->now,
                ]);
            }
        }
    }

    /**
     * Bulk update using whereIn
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'bulk'])]
    public function benchBulkUpdateWhereIn(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        DB::table('benchmark_products')
            ->whereIn('id', $idsToUpdate)
            ->update([
                'price' => 99.99,
                'updated_at' => $this->now,
            ]);
    }

    /**
     * Conditional bulk update
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'bulk'])]
    public function benchConditionalUpdate(): void
    {
        DB::table('benchmark_products')
            ->where('stock', '<', 10)
            ->update([
                'is_active' => false,
                'updated_at' => $this->now,
            ]);
    }

    /**
     * Update with increment/decrement
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database'])]
    public function benchIncrementUpdate(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        foreach ($idsToUpdate as $id) {
            DB::table('benchmark_products')
                ->where('id', $id)
                ->increment('stock', 5);
        }
    }

    /**
     * Bulk increment using whereIn
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'bulk'])]
    public function benchBulkIncrement(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        DB::table('benchmark_products')
            ->whereIn('id', $idsToUpdate)
            ->increment('stock', 5);
    }

    /**
     * Update with upsert (MySQL 8.0+)
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'upsert'])]
    public function benchUpsert(): void
    {
        $records = [];
        $idsToUpdate = array_slice($this->recordIds, 0, 100);

        foreach ($idsToUpdate as $id) {
            $records[] = [
                'id' => $id,
                'name' => "Updated Product {$id}",
                'price' => (($id * 7) % 990) + 10,
                'stock' => ($id * 3) % 100,
                'is_active' => true,
            ];
        }

        DB::table('benchmark_products')->upsert(
            $records,
            ['id'], // Unique identifier
            ['name', 'price', 'stock', 'is_active'] // Columns to update
        );
    }

    /**
     * Chunked updates for large datasets
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(0)]
    #[Bench\Groups(['update', 'database', 'chunked'])]
    public function benchChunkedUpdate(): void
    {
        DB::table('benchmark_products')
            ->where('is_active', true)
            ->chunkById(100, function ($products) {
                $ids = $products->pluck('id')->toArray();

                DB::table('benchmark_products')
                    ->whereIn('id', $ids)
                    ->update([
                        'price' => DB::raw('price * 1.1'), // 10% price increase
                        'updated_at' => $this->now,
                    ]);
            });
    }
}
