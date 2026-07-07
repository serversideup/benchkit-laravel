<?php

namespace App\Benchmarks\Php\Performance;

use Illuminate\Support\Facades\Hash;
use PhpBench\Attributes as Bench;

class BcryptBenchmark
{
    private string $password;

    private string $hashedPassword;

    public function __construct()
    {
        $this->password = 'MySecureP@ssw0rd123!';
        // Pre-generate a hash for verification benchmarks
        $this->hashedPassword = Hash::make($this->password);
    }

    /**
     * Benchmark bcrypt hashing with default cost (10)
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['bcrypt', 'hash-make'])]
    public function benchHashMakeDefaultCost(): void
    {
        Hash::make($this->password);
    }

    /**
     * Benchmark bcrypt hashing with cost factor 12
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['bcrypt', 'hash-make'])]
    public function benchHashMakeCost12(): void
    {
        Hash::make($this->password, ['rounds' => 12]);
    }

    /**
     * Benchmark bcrypt password verification
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['bcrypt', 'hash-check'])]
    public function benchHashCheck(): void
    {
        Hash::check($this->password, $this->hashedPassword);
    }

    /**
     * Benchmark multiple sequential hashing operations
     */
    #[Bench\Revs(20)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Groups(['bcrypt', 'hash-multiple'])]
    public function benchMultipleHashOperations(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Hash::make($this->password.$i);
        }
    }
}
