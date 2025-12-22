<?php

namespace App\Benchmarks\Php\Performance;

use PhpBench\Attributes as Bench;

class ArrayBenchmark
{
    private array $smallArray;
    private array $largeArray;
    private array $associativeArray;
    private array $multidimensionalArray;
    private array $unsortedArray;

    public function __construct()
    {
        // Small array - 100 elements
        $this->smallArray = range(1, 100);

        // Large array - 10,000 elements
        $this->largeArray = range(1, 10000);

        // Associative array
        $this->associativeArray = [];
        for ($i = 0; $i < 5000; $i++) {
            $this->associativeArray["key_{$i}"] = [
                'id' => $i,
                'value' => rand(0, 1000),
                'name' => "Item {$i}"
            ];
        }

        // Multidimensional array
        $this->multidimensionalArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $this->multidimensionalArray[] = [
                'id' => $i,
                'data' => range(1, 10),
                'nested' => [
                    'value' => rand(0, 100),
                    'tags' => ['tag1', 'tag2', 'tag3']
                ]
            ];
        }

        // Unsorted array for sorting benchmarks
        $this->unsortedArray = $this->largeArray;
        shuffle($this->unsortedArray);
    }

    /**
     * Benchmark array_map on large array
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-map'])]
    public function benchArrayMap(): void
    {
        array_map(fn($x) => $x * 2, $this->largeArray);
    }

    /**
     * Benchmark array_filter on large array
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-filter'])]
    public function benchArrayFilter(): void
    {
        array_filter($this->largeArray, fn($x) => $x % 2 === 0);
    }

    /**
     * Benchmark array_reduce on large array
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-reduce'])]
    public function benchArrayReduce(): void
    {
        array_reduce($this->largeArray, fn($carry, $item) => $carry + $item, 0);
    }

    /**
     * Benchmark sorting with sort()
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'sort'])]
    public function benchSort(): void
    {
        $arr = $this->unsortedArray;
        sort($arr);
    }

    /**
     * Benchmark sorting with usort()
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'usort'])]
    public function benchUsort(): void
    {
        $arr = $this->unsortedArray;
        usort($arr, fn($a, $b) => $b <=> $a);
    }

    /**
     * Benchmark array_merge on large arrays
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-merge'])]
    public function benchArrayMerge(): void
    {
        array_merge($this->smallArray, $this->smallArray, $this->smallArray);
    }

    /**
     * Benchmark array_diff
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-diff'])]
    public function benchArrayDiff(): void
    {
        array_diff($this->largeArray, array_slice($this->largeArray, 0, 5000));
    }

    /**
     * Benchmark array_intersect
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-intersect'])]
    public function benchArrayIntersect(): void
    {
        array_intersect($this->largeArray, array_slice($this->largeArray, 2500, 5000));
    }

    /**
     * Benchmark array_unique
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-unique'])]
    public function benchArrayUnique(): void
    {
        $duplicates = array_merge($this->smallArray, $this->smallArray);
        array_unique($duplicates);
    }

    /**
     * Benchmark array_column on multidimensional array
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-column'])]
    public function benchArrayColumn(): void
    {
        array_column($this->multidimensionalArray, 'id');
    }

    /**
     * Benchmark in_array search
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'in-array'])]
    public function benchInArray(): void
    {
        in_array(9999, $this->largeArray);
    }

    /**
     * Benchmark array_search
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-search'])]
    public function benchArraySearch(): void
    {
        array_search(9999, $this->largeArray);
    }

    /**
     * Benchmark array_keys on associative array
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-keys'])]
    public function benchArrayKeys(): void
    {
        array_keys($this->associativeArray);
    }

    /**
     * Benchmark array_values on associative array
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'array-values'])]
    public function benchArrayValues(): void
    {
        array_values($this->associativeArray);
    }

    /**
     * Benchmark combined operations (real-world scenario)
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['array', 'combined'])]
    public function benchCombinedOperations(): void
    {
        $result = array_map(
            fn($x) => $x * 2,
            array_filter($this->largeArray, fn($x) => $x % 2 === 0)
        );
        sort($result);
    }
}