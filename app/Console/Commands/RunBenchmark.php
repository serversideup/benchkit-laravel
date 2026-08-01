<?php

namespace App\Console\Commands;

use App\Actions\Runs\SaveRunFromState;
use App\Support\BenchmarkProcess;
use App\Support\BenchmarkStages;
use App\Support\RunState;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs every stage of the benchmark queue described by the current run
 * state, writing console output to the run's live log as it goes.
 *
 * This is spawned detached from the HTTP request that asked for the run, so
 * the run belongs to the server rather than to a browser tab: reloading,
 * closing the tab, or watching from a different machine changes nothing,
 * and the run still saves its snapshot when it finishes unobserved.
 */
class RunBenchmark extends Command
{
    protected $signature = 'benchmark:run';

    protected $description = 'Run the benchmark stages requested by the current run state';

    public function handle(RunState $state, BenchmarkStages $stages): int
    {
        $run = $state->current();

        if ($run === null || $run['status'] !== RunState::STATUS_RUNNING) {
            $this->error('There is no benchmark run waiting to be started.');

            return self::FAILURE;
        }

        $state->claim(getmypid());

        $settings = $run['settings'] ?? [];

        foreach ($stages->enabled($settings) as $stage) {
            if ($state->cancelRequested()) {
                return $this->cancel($state);
            }

            $this->runStage($state, $stages, $stage, $settings);

            if ($state->cancelRequested()) {
                return $this->cancel($state);
            }
        }

        $state->finish(RunState::STATUS_COMPLETED);

        (new SaveRunFromState)->execute($state);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function runStage(RunState $state, BenchmarkStages $stages, string $stage, array $settings): void
    {
        $state->startStage($stage);
        $this->log($state, $stage, 'out', sprintf('=== %s ===', $stages->label($stage)));

        try {
            ['command' => $command, 'collect' => $collect] = $stages->resolve($stage, $settings);
        } catch (Throwable $exception) {
            $this->log($state, $stage, 'err', $exception->getMessage());
            $state->finishStage($stage, 'error');

            return;
        }

        $process = new BenchmarkProcess(
            $command,
            fn (array $event) => $state->appendEvent([...$event, 'stage' => $stage]),
        );

        if ($collect !== null) {
            $process->collectOutputTo($collect);
        }

        $successful = $process
            ->cancelWhen(fn () => $state->cancelRequested())
            ->run();

        if ($state->cancelRequested()) {
            return;
        }

        if (! $successful) {
            $this->log($state, $stage, 'err', $process->exitCodeText() ?? 'The benchmark exited unsuccessfully.');
        }

        $state->finishStage($stage, $successful ? 'completed' : 'error');
    }

    /**
     * A cancelled run is deliberately not saved: the user asked for it to
     * stop, and a half-finished run would sit in their history looking like
     * a real result.
     */
    protected function cancel(RunState $state): int
    {
        $state->finish(RunState::STATUS_CANCELLED, RunState::SAVE_EMPTY);

        return self::SUCCESS;
    }

    protected function log(RunState $state, string $stage, string $type, string $output): void
    {
        $state->appendEvent([
            'stage' => $stage,
            'type' => $type,
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
