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
 * - Conditional deletes
 * - Truncate operations
 *
 * Rows are seeded with explicit, deterministic ids and values so every run and
 * every host operates on identical data.
 *
 * Deletes are destructive, so the dataset has to be rebuilt between
 * measurements. That rebuild used to happen inside each subject, where phpbench
 * charged it to the delete — a 100-row delete was really timing a 100-row
 * delete plus a 100-row insert. One revolution per iteration moves the rebuild
 * into setUp(), which runs untimed, so a delete measures only the delete.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
class DeleteBenchmark extends BaseBenchmark
{
    private array $recordIds = [];

    private int $totalRecords = 2000; // More records since we're deleting them

    /** Resolved in setUp() so benchDeleteOldRecords() times only the DELETE. */
    private string $cutoffDate;

    /** The "older than 30 days" side of the seed, on the far side of the cutoff. */
    private string $archivedDate;

    public function setUp(): void
    {
        parent::setUp();

        $format = DB::connection()->getQueryGrammar()->getDateFormat();
        $this->cutoffDate = now()->subDays(30)->format($format);
        $this->archivedDate = now()->subDays(60)->format($format);

        $this->resetTestTable('benchmark_products', function ($table) {
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
            $records[] = $this->makeRecord($i);
        }

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('benchmark_products')->insert($chunk);
        }

        $this->recordIds = range(1, $this->totalRecords);
    }

    /**
     * Build a deterministic record for the given seed index. Explicit ids and
     * timestamps resolved once per iteration keep the dataset identical across
     * revolutions and runs — a clock read per row can straddle a second
     * boundary and move how many rows the date-based subjects match.
     *
     * @return array{id: int, name: string, price: int, stock: int, is_active: bool, created_at: string, updated_at: string}
     */
    private function makeRecord(int $index): array
    {
        return [
            'id' => $index + 1,
            'name' => "Product {$index}",
            'price' => ($index % 990) + 10,
            'stock' => intdiv($index, 2) % 100,
            'is_active' => $index % 2 === 0, // Half active, half inactive
            'created_at' => $index % 4 === 0 ? $this->archivedDate : $this->now,
            'updated_at' => $this->now,
        ];
    }

    /**
     * Delete records individually using Query Builder
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchQueryBuilderIndividual(): void
    {
        $idsToDelete = array_slice($this->recordIds, 0, 100);

        foreach ($idsToDelete as $id) {
            DB::table('benchmark_products')
                ->where('id', $id)
                ->delete();
        }
    }

    /**
     * Conditional delete (delete inactive records)
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchConditionalDelete(): void
    {
        DB::table('benchmark_products')
            ->where('is_active', false)
            ->delete();
    }

    /**
     * Delete with multiple conditions
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchMultiConditionDelete(): void
    {
        DB::table('benchmark_products')
            ->where('is_active', false)
            ->where('stock', 0)
            ->delete();
    }

    /**
     * Delete using raw SQL
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database', 'raw'])]
    public function benchRawDelete(): void
    {
        $idsToDelete = array_slice($this->recordIds, 0, 100);
        $idList = implode(',', $idsToDelete);

        DB::statement("DELETE FROM benchmark_products WHERE id IN ({$idList})");
    }

    /**
     * Delete old records (date-based)
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchDeleteOldRecords(): void
    {
        // Deletes records "older than 30 days" — a quarter of the seed data
        DB::table('benchmark_products')
            ->where('created_at', '<', $this->cutoffDate)
            ->delete();
    }

    /**
     * Truncate table (fastest for complete wipe)
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database', 'truncate'])]
    public function benchTruncate(): void
    {
        DB::table('benchmark_products')->truncate();
    }

    /**
     * Delete with subquery
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(15)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['delete', 'database'])]
    public function benchDeleteWithSubquery(): void
    {
        // Delete products with below-average price
        DB::table('benchmark_products')
            ->whereRaw('price < (SELECT AVG(price) FROM benchmark_products)')
            ->delete();
    }
}
