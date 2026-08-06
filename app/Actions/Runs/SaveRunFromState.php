<?php

namespace App\Actions\Runs;

use App\Support\RunState;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Freezes the finished run described by the run state into a durable
 * snapshot.
 *
 * The run process does this itself rather than leaving it to the browser,
 * so a run that finishes with no tab open is still saved. The outcome is
 * recorded back onto the run state ('saved', 'empty', or 'failed') for
 * clients to read, and a failed save can be retried without re-running
 * anything, because the results and the console log are both on disk.
 */
class SaveRunFromState
{
    /**
     * @return array<string, mixed> the updated run state
     */
    public function execute(RunState $state): array
    {
        $run = $state->current() ?? [];
        $stages = $state->completedStages();

        if ($stages === []) {
            return $state->recordSnapshot(RunState::SAVE_EMPTY);
        }

        try {
            $snapshot = (new CreateRunSnapshot)->execute([
                'stages_completed' => $stages,
                'settings' => $run['settings'] ?? [],
                'preset' => $run['preset'] ?? null,
                'logs' => $state->logsByStage(),
                ...$this->hostDetails($run['host_details'] ?? [], $stages),
            ]);

            return $state->recordSnapshot(RunState::SAVE_SAVED, $snapshot['id']);
        } catch (Throwable $exception) {
            Log::error('Saving the benchmark run snapshot failed.', ['exception' => $exception]);

            return $state->recordSnapshot(RunState::SAVE_FAILED);
        }
    }

    /**
     * Details the user entered on a previous run carry forward; a
     * remembered host outranks the network guess.
     *
     * @param  array<string, mixed>  $remembered
     * @param  array<int, string>  $stages
     * @return array<string, mixed>
     */
    protected function hostDetails(array $remembered, array $stages): array
    {
        $provider = $remembered['provider'] ?? null;
        $source = 'user';

        if ($provider === null && in_array('cfspeedtest', $stages, true)) {
            $provider = (new DetectProvider)->execute();
            $source = 'ripe';
        }

        return [
            'provider' => $provider,
            'provider_source' => $provider !== null ? $source : null,
            'plan' => $remembered['plan'] ?? null,
            'datacenter' => $remembered['datacenter'] ?? null,
            // CreateRunSnapshot normalizes this — a remembered cost can still
            // be free text from a client that hasn't reloaded yet.
            'cost' => $remembered['cost'] ?? null,
        ];
    }
}
