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
        
        // Create test table
        $this->ensureTestTable('benchmark_products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        // Seed with test data
        $records = [];
        for ($i = 0; $i < $this->totalRecords; $i++) {
            $records[] = [
                'name' => "Product {$i}",
                'price' => rand(10, 1000),
                'stock' => rand(0, 100),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
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
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['update', 'database'])]
    public function benchQueryBuilderIndividual(): void
    {
        // Update 100 random records
        $idsToUpdate = array_slice($this->recordIds, 0, 100);
        
        foreach ($idsToUpdate as $id) {
            DB::table('benchmark_products')
                ->where('id', $id)
                ->update([
                    'price' => rand(10, 1000),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Update records individually using Eloquent
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['update', 'database', 'eloquent'])]
    public function benchEloquentIndividual(): void
    {
        // Update 100 random records
        $idsToUpdate = array_slice($this->recordIds, 0, 100);
        
        foreach ($idsToUpdate as $id) {
            $model =BenchmarkEloquentModel::find($id);
            
            if ($model) {
                $model->update([
                    'price' => rand(10, 1000),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Bulk update using whereIn
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['update', 'database', 'bulk'])]
    public function benchBulkUpdateWhereIn(): void
    {
        $idsToUpdate = array_slice($this->recordIds, 0, 100);
        
        DB::table('benchmark_products')
            ->whereIn('id', $idsToUpdate)
            ->update([
                'price' => 99.99,
                'updated_at' => now(),
            ]);
    }

    /**
     * Conditional bulk update
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['update', 'database', 'bulk'])]
    public function benchConditionalUpdate(): void
    {
        DB::table('benchmark_products')
            ->where('stock', '<', 10)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Update with increment/decrement
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
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
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
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
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['update', 'database', 'upsert'])]
    public function benchUpsert(): void
    {
        $records = [];
        $idsToUpdate = array_slice($this->recordIds, 0, 100);
        
        foreach ($idsToUpdate as $id) {
            $records[] = [
                'id' => $id,
                'name' => "Updated Product {$id}",
                'price' => rand(10, 1000),
                'stock' => rand(0, 100),
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
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
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
                        'updated_at' => now(),
                    ]);
            });
    }
}