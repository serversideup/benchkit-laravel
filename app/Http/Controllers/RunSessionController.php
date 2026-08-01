<?php

namespace App\Http\Controllers;

use App\Actions\Runs\SaveRunFromState;
use App\Http\Requests\Runs\StartRunRequest;
use App\Support\BenchmarkStages;
use App\Support\RunState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

/**
 * The run currently in progress, as every client sees it.
 *
 * Only one benchmark may run at a time on a machine — they compete for the
 * CPU, disk, and network they are measuring — and because the run is owned
 * by a detached process rather than by whoever started it, that limit holds
 * across tabs, browsers, and devices alike. Any client can watch the live
 * console from wherever it left off, and any client can stop the run.
 */
class RunSessionController extends Controller
{
    public function __construct(protected RunState $state) {}

    public function store(StartRunRequest $request, BenchmarkStages $stages): JsonResponse
    {
        if ($this->state->isActive()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'A benchmark is already running.',
                'run' => $this->payload($this->state->current()),
            ], 409);
        }

        $settings = $request->validated('settings');
        $enabled = $stages->enabled($settings);

        if ($enabled === []) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No benchmark stages were selected.',
            ], 422);
        }

        $run = $this->state->start(
            $settings,
            $enabled,
            $request->validated('preset'),
            $request->validated('host_details') ?? [],
        );

        $this->spawn();

        return response()->json(['run' => $this->payload($run)], 201);
    }

    /**
     * The run's state together with the console output written since the
     * client's last read.
     *
     * Clients poll this rather than hold a stream open: a run can be watched
     * from several places at once, and every open stream would occupy a PHP
     * worker — including during the HTTP stage, which load tests this very
     * application and would then be measuring a server it had tied up.
     */
    public function log(Request $request): JsonResponse
    {
        $run = $this->state->current();

        if ($run === null) {
            return response()->json(['run' => null, 'offset' => 0, 'events' => []]);
        }

        $log = $this->state->eventsSince(max(0, (int) $request->query('offset', 0)));

        return response()->json([
            'run' => $this->payload($run),
            'offset' => $log['offset'],
            'events' => $log['events'],
        ]);
    }

    public function cancel(): JsonResponse
    {
        $run = $this->state->current();

        if ($run === null) {
            return response()->json(['run' => null], 404);
        }

        if ($run['status'] === RunState::STATUS_RUNNING) {
            $this->state->requestCancel();
        }

        return response()->json([
            'run' => $this->payload($this->state->current()),
            'cancel_requested' => $this->state->cancelRequested(),
        ]);
    }

    /**
     * Retry the snapshot for a run that finished but failed to save. The
     * results and console log are both on disk, so nothing is re-run.
     */
    public function save(): JsonResponse
    {
        $run = $this->state->current();

        if ($run === null || $run['status'] === RunState::STATUS_RUNNING) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'There is no finished run to save.',
            ], 409);
        }

        return response()->json(['run' => $this->payload((new SaveRunFromState)->execute($this->state))]);
    }

    /**
     * Forget a finished run so the app returns to its idle state.
     */
    public function destroy(): JsonResponse
    {
        if ($this->state->isActive()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Cancel the running benchmark before dismissing it.',
            ], 409);
        }

        $this->state->dismiss();

        return response()->json(['run' => null]);
    }

    /**
     * Launch the run detached from this request. The `&` returns the shell
     * immediately and `nohup` keeps the run alive once it exits, which is
     * what frees the benchmark from the browser connection that asked for
     * it. The run records its own PID on boot.
     */
    protected function spawn(): void
    {
        Process::path(base_path())->run(sprintf(
            'nohup %s %s benchmark:run > /dev/null 2>&1 &',
            config('benchmark.php_binary'),
            escapeshellarg(base_path('artisan')),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $run
     * @return array<string, mixed>|null
     */
    protected function payload(?array $run): ?array
    {
        if ($run === null) {
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
            // Cancelling is cooperative — the run stops at its next check —
            // so clients need to distinguish "running" from "stopping".
            'cancel_requested' => $this->state->cancelRequested(),
        ];
    }
}
