<?php

namespace App\Http\Controllers;

use App\Actions\Runs\ListRuns;
use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;
use App\Support\RunState;
use Inertia\Inertia;
use Inertia\Response;

class BenchmarkController extends Controller
{
    public function __construct(protected RunState $state) {}

    public function index(): Response
    {
        return Inertia::render('Index', [
            'server' => (new ServerSpecs)->execute(),
            'php' => (new PhpSpecs)->execute(),
            'laravel' => (new LaravelSpecs)->execute(),
            'recentRuns' => array_slice((new ListRuns)->execute(), 0, 3),
            // A run belongs to the server, not to the tab that started it,
            // so a page loaded anywhere picks up one already in progress.
            'activeRun' => $this->activeRun(),
        ]);
    }

    /**
     * The run worth showing on load: one still going, or one that stopped
     * in a way the user has not seen yet. A run that finished cleanly is
     * left out — it is already in the run history below the Start button.
     *
     * @return array<string, mixed>|null
     */
    protected function activeRun(): ?array
    {
        $run = $this->state->current();

        if ($run === null) {
            return null;
        }

        if (! in_array($run['status'], [RunState::STATUS_RUNNING, RunState::STATUS_INTERRUPTED], true)) {
            return null;
        }

        return [
            'id' => $run['id'],
            'status' => $run['status'],
            'started_at' => $run['started_at'],
            'ended_at' => $run['ended_at'],
            'current_stage' => $run['current_stage'],
            'stages' => $run['stages'],
            'settings' => $run['settings'],
            'preset' => $run['preset'],
            'save_state' => $run['save_state'],
            'snapshot_id' => $run['snapshot_id'],
            'error' => $run['error'],
            'cancel_requested' => $this->state->cancelRequested(),
        ];
    }
}
