<?php

namespace App\Console\Commands;

use App\Support\RunState;
use Illuminate\Console\Command;

/**
 * Escape hatch for a run the app will not let go of.
 *
 * An abandoned run normally clears itself — a run whose process is gone is
 * reconciled to "interrupted" the next time anyone reads its state — so
 * this is only needed when the process is somehow still alive but wedged
 * and the in-app Cancel button is not getting through.
 */
class ClearBenchmarkRun extends Command
{
    protected $signature = 'benchmark:clear-run {--force : Clear a run whose process is still alive}';

    protected $description = 'Forget the current benchmark run so a new one can be started';

    public function handle(RunState $state): int
    {
        $run = $state->current();

        if ($run === null) {
            $this->info('No benchmark run is recorded.');

            return self::SUCCESS;
        }

        if ($run['status'] === RunState::STATUS_RUNNING) {
            $this->warn("Run {$run['id']} is still running (PID {$run['pid']}).");
            $this->line('Clearing it here only makes the app forget it — the benchmark process keeps running and keeps loading this machine.');

            if (! $this->option('force') && ! $this->confirm('Clear it anyway?', false)) {
                return self::FAILURE;
            }

            $state->finish(RunState::STATUS_INTERRUPTED, RunState::SAVE_EMPTY, null, 'The run was cleared manually.');
        }

        $state->dismiss();

        $this->info('Benchmark run cleared.');

        return self::SUCCESS;
    }
}
