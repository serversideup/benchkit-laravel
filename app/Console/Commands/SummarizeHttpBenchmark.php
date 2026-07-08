<?php

namespace App\Console\Commands;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\HttpSummaryReport;
use Illuminate\Console\Command;

/**
 * Prints a detailed console summary for one completed HTTP load-test route.
 * Invoked from the streamed benchmark subprocess after oha writes its JSON,
 * so the run's live log shows the full result rather than just "Completed".
 */
class SummarizeHttpBenchmark extends Command
{
    protected $signature = 'benchmark:http-summary {route}';

    protected $description = 'Print a detailed summary of a completed HTTP load-test route from its oha JSON';

    public function handle(): int
    {
        $route = $this->argument('route');

        $detail = (new HttpBenchmarkResults)->detail($route);

        if ($detail === null) {
            $this->line("  No results were captured for {$route}.");

            return self::SUCCESS;
        }

        foreach ((new HttpSummaryReport)->lines($detail) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
