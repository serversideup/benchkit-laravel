<?php

namespace App\Console\Commands;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\HttpSummaryReport;
use Illuminate\Console\Command;

/**
 * Prints a detailed console summary for one completed measured window.
 *
 * A window is addressed by its slot — `static-c20` for a sweep level,
 * `io-latency` for a response-time pass — because a route no longer has one
 * result. Useful for reading a finished results directory by hand; the run's
 * own console gets its lines from the driver as each window completes.
 */
class SummarizeHttpBenchmark extends Command
{
    protected $signature = 'benchmark:http-summary {slot : A measured window, such as static-c20 or io-latency}';

    protected $description = 'Print a detailed summary of one completed measured window from its oha JSON';

    public function handle(): int
    {
        $slot = $this->argument('slot');
        $results = new HttpBenchmarkResults;
        $path = $results->pathForSlot($slot);

        if ($path === null) {
            $this->error("{$slot} is not a measured window this run could have produced.");

            return self::FAILURE;
        }

        $detail = $results->detail($slot, $path);

        if ($detail === null) {
            $this->line("  No results were captured for {$slot}.");

            return self::SUCCESS;
        }

        foreach ((new HttpSummaryReport)->lines($detail) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
