<?php

namespace App\Benchmarks\Php\Performance;

use PhpBench\Attributes as Bench;

class JsonBenchmark
{
    private array $smallData;

    private array $largeData;

    private array $deeplyNestedData;

    private string $smallJson;

    private string $largeJson;

    private string $deeplyNestedJson;

    public function __construct()
    {
        // Small dataset - typical API response
        $this->smallData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-01-01 12:00:00',
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ];

        // Large dataset - collection of 1000 records
        $this->largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $this->largeData[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'active' => $i % 2 === 0,
                'score' => ($i * 7) % 100,
                'tags' => ['tag1', 'tag2', 'tag3'],
                'metadata' => [
                    'last_login' => '2024-01-01 12:00:00',
                    'ip_address' => "192.168.1.{$i}",
                ],
            ];
        }

        // Deeply nested structure
        $this->deeplyNestedData = $this->createNestedArray(10);

        // Pre-encode for decoding benchmarks
        $this->smallJson = json_encode($this->smallData);
        $this->largeJson = json_encode($this->largeData);
        $this->deeplyNestedJson = json_encode($this->deeplyNestedData);
    }

    /**
     * A branching factor of 2 keeps the tree at ~2^depth nodes — a factor of
     * 5 produced ~10 million nodes at depth 10 and exhausted memory before
     * any subject could run.
     */
    private function createNestedArray(int $depth, int $current = 0): array
    {
        if ($current >= $depth) {
            return ['value' => 'leaf'];
        }

        return [
            'level' => $current,
            'data' => array_fill(0, 2, $this->createNestedArray($depth, $current + 1)),
        ];
    }

    /**
     * Benchmark encoding small data
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'encode'])]
    public function benchEncodeSmall(): void
    {
        json_encode($this->smallData);
    }

    /**
     * Benchmark encoding large data
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'encode'])]
    public function benchEncodeLarge(): void
    {
        json_encode($this->largeData);
    }

    /**
     * Benchmark encoding deeply nested data
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'encode'])]
    public function benchEncodeDeeplyNested(): void
    {
        json_encode($this->deeplyNestedData);
    }

    /**
     * Benchmark decoding small data
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'decode'])]
    public function benchDecodeSmall(): void
    {
        json_decode($this->smallJson, true);
    }

    /**
     * Benchmark decoding large data
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'decode'])]
    public function benchDecodeLarge(): void
    {
        json_decode($this->largeJson, true);
    }

    /**
     * Benchmark decoding deeply nested data
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'decode'])]
    public function benchDecodeDeeplyNested(): void
    {
        json_decode($this->deeplyNestedJson, true);
    }

    /**
     * Benchmark round-trip encoding and decoding
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['json', 'round-trip'])]
    public function benchRoundTripSmall(): void
    {
        $encoded = json_encode($this->smallData);
        json_decode($encoded, true);
    }
}
