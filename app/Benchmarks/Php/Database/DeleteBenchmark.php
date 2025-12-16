<?php

namespace App\Benchmarks\Php\Database;

use App\Benchmarks\Php\BaseBenchmark;
use Illuminate\Support\Facades\DB;
use PhpBench\Attributes as Bench;

/**
 * Delete Benchmark
 * 
 * Compares different deletion strategies:
 * - Individual vs bulk deletes
 * - Hard vs soft deletes
 * - Conditional deletes
 * - Truncate operations
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
class DeleteBenchmark extends BaseBenchmark
{
    private array $recordIds = [];
    private int $totalRecords = 2000; // More records since we're deleting them

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
            $table->softDeletes();
            $table->index(['is_active', 'created_at']);
        });

        $this->seedData();
    }

    public function tearDown(): void
    {
        $this->dropTestTable('benchmark_products');
        parent::tearDown();
    }

    private function seedData(): void
    {
        $records = [];
        for ($i = 0; $i < $this->totalRecords; $i++) {
            $records[] = [
                'name' => "Product {$i}",
                'price' => rand(10, 1000),
                'stock' => rand(0, 100),
                'is_active' => $i % 2 === 0, // Half active, half inactive
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        DB::table('benchmark_products')->insert($records);
        $this->recordIds = DB::table('benchmark_products')->pluck('id')->toArray();
    }

    /**
     * Delete records individually using Query Builder
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchQueryBuilderIndividual(): void
    {
        // Delete 100 records one by one
        $idsToDelete = array_slice($this->recordIds, 0, 100);
        
        foreach ($idsToDelete as $id) {
            DB::table('benchmark_products')
                ->where('id', $id)
                ->delete();
        }
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Conditional delete (delete inactive records)
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchConditionalDelete(): void
    {
        DB::table('benchmark_products')
            ->where('is_active', false)
            ->delete();
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Delete with multiple conditions
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchMultiConditionDelete(): void
    {
        DB::table('benchmark_products')
            ->where('is_active', false)
            ->where('stock', 0)
            ->delete();
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Delete using raw SQL
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database', 'raw'])]
    public function benchRawDelete(): void
    {
        $idsToDelete = array_slice($this->recordIds, 0, 100);
        $idList = implode(',', $idsToDelete);
        
        DB::statement("DELETE FROM benchmark_products WHERE id IN ({$idList})");
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Delete old records (date-based)
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchDeleteOldRecords(): void
    {
        // Delete records "older than 30 days"
        $cutoffDate = now()->subDays(30);
        
        DB::table('benchmark_products')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Truncate table (fastest for complete wipe)
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database', 'truncate'])]
    public function benchTruncate(): void
    {
        DB::table('benchmark_products')->truncate();
        
        // Re-seed for next iteration
        $this->seedData();
    }

    /**
     * Delete with subquery
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchDeleteWithSubquery(): void
    {
        // Delete products with below-average price
        DB::table('benchmark_products')
            ->whereRaw('price < (SELECT AVG(price) FROM benchmark_products)')
            ->delete();
        
        // Re-seed for next iteration
        $this->seedData();
    }
}