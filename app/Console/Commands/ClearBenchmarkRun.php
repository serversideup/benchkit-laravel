<?php

namespace App\Console\Commands;

use App\Support\StreamedProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearBenchmarkLock extends Command
{
    protected $signature = 'benchmark:clear-lock';

    protected $description = 'Release the benchmark run lock left behind by an interrupted run';

    public function handle(): int
    {
        Cache::lock(StreamedProcess::LOCK_KEY)->forceRelease();
        Cache::forget(StreamedProcess::HEARTBEAT_KEY);

        $this->info('Benchmark run lock cleared.');

        return self::SUCCESS;
    }
}
