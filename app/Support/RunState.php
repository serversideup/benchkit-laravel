<?php

namespace App\Support;

use App\Actions\Runs\CreateRunSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The durable record of the benchmark run in progress.
 *
 * A run is owned by a detached subprocess, not by the browser connection
 * that asked for it: closing the tab, reloading, or opening the app in a
 * second browser does not affect the run, and every one of those clients
 * reads the same state from here. That is also what enforces one run at a
 * time — the claim is held by a live PID rather than by an HTTP request.
 *
 * Three files back it:
 *   run.json  the run record (status, owning PID, per-stage progress)
 *   run.log   the console output, one JSON event per line, append-only
 *   cancel    a flag file the running process polls
 */
class RunState
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_INTERRUPTED = 'interrupted';

    /**
     * How the snapshot at the end of a run turned out. 'empty' means every
     * stage failed or was skipped, so there was nothing worth saving.
     */
    public const SAVE_PENDING = 'pending';

    public const SAVE_SAVED = 'saved';

    public const SAVE_EMPTY = 'empty';

    public const SAVE_FAILED = 'failed';

    /**
     * Begin a run, replacing any finished run's record. Callers must check
     * {@see isActive()} first — this does not itself refuse to overwrite a
     * live run.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $stages
     * @param  array<string, mixed>  $hostDetails
     * @return array<string, mixed>
     */
    public function start(array $settings, array $stages, ?string $preset, array $hostDetails = []): array
    {
        File::ensureDirectoryExists($this->directory());
        File::delete($this->cancelPath());
        File::put($this->logPath(), '');

        return $this->write([
            'id' => now()->utc()->format('Ymd-His').'-'.Str::lower(Str::random(4)),
            'status' => self::STATUS_RUNNING,
            'pid' => null,
            'started_at' => now()->utc()->toIso8601String(),
            'ended_at' => null,
            'settings' => $settings,
            'preset' => $preset,
            'host_details' => $hostDetails,
            'current_stage' => null,
            'stages' => $this->initialStages($stages),
            'save_state' => self::SAVE_PENDING,
            'snapshot_id' => null,
            'error' => null,
        ]);
    }

    /**
     * The current run record, or null when no run has been started.
     *
     * A record left saying "running" by a process that is no longer alive
     * (container restart, OOM kill) is reconciled to interrupted here, so
     * every reader self-heals rather than waiting on a lock to expire.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $run = $this->read();

        if ($run === null) {
            return null;
        }

        if ($run['status'] === self::STATUS_RUNNING && ! $this->ownerIsAlive($run)) {
            return $this->write([
                ...$run,
                'status' => self::STATUS_INTERRUPTED,
                'ended_at' => now()->utc()->toIso8601String(),
                'error' => $run['pid'] === null
                    ? 'The benchmark process never started. Check that a PHP CLI binary is available — set BENCHMARK_PHP_BINARY if it is not on the PATH.'
                    : 'The benchmark process stopped without finishing. The server may have restarted mid-run.',
            ]);
        }

        return $run;
    }

    /**
     * Whether a run currently holds the session. False for a finished run,
     * so its record can stay on disk for clients still watching it.
     */
    public function isActive(): bool
    {
        return ($this->current()['status'] ?? null) === self::STATUS_RUNNING;
    }

    /**
     * Adopt the run as the calling process. Until a PID is recorded a run
     * is treated as alive on the strength of its start time, which covers
     * the moment between the HTTP request writing the record and the
     * detached process booting far enough to claim it.
     *
     * @return array<string, mixed>
     */
    public function claim(int $pid): array
    {
        return $this->merge(['pid' => $pid]);
    }

    /**
     * @return array<string, mixed>
     */
    public function startStage(string $stage): array
    {
        $run = $this->read() ?? [];
        $stages = $run['stages'] ?? [];
        $stages[$stage] = [
            ...($stages[$stage] ?? []),
            'status' => 'running',
            'started_at' => now()->utc()->toIso8601String(),
            'ended_at' => null,
        ];

        return $this->merge(['current_stage' => $stage, 'stages' => $stages]);
    }

    /**
     * @return array<string, mixed>
     */
    public function finishStage(string $stage, string $status): array
    {
        $run = $this->read() ?? [];
        $stages = $run['stages'] ?? [];
        $stages[$stage] = [
            ...($stages[$stage] ?? []),
            'status' => $status,
            'ended_at' => now()->utc()->toIso8601String(),
        ];

        return $this->merge(['stages' => $stages]);
    }

    /**
     * @return array<int, string>
     */
    public function completedStages(): array
    {
        $stages = $this->read()['stages'] ?? [];

        return array_values(array_filter(
            CreateRunSnapshot::STAGES,
            fn (string $stage) => ($stages[$stage]['status'] ?? null) === 'completed',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function finish(string $status, string $saveState = self::SAVE_PENDING, ?string $snapshotId = null, ?string $error = null): array
    {
        return $this->merge([
            'status' => $status,
            'current_stage' => null,
            'ended_at' => now()->utc()->toIso8601String(),
            'save_state' => $saveState,
            'snapshot_id' => $snapshotId,
            'error' => $error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSnapshot(string $saveState, ?string $snapshotId = null): array
    {
        return $this->merge(['save_state' => $saveState, 'snapshot_id' => $snapshotId]);
    }

    /**
     * Ask the running process to stop. The flag is a file rather than a
     * signal so any client can request cancellation without knowing
     * anything about the process, and so the request survives a moment
     * where the process is between stages.
     */
    public function requestCancel(): void
    {
        File::ensureDirectoryExists($this->directory());
        File::put($this->cancelPath(), (string) time());
    }

    public function cancelRequested(): bool
    {
        return File::exists($this->cancelPath());
    }

    /**
     * Discard a finished run's record so the app returns to its idle state.
     * A live run is left alone — cancel it first.
     */
    public function dismiss(): void
    {
        if ($this->isActive()) {
            return;
        }

        File::delete([$this->statePath(), $this->logPath(), $this->cancelPath()]);
    }

    /**
     * Append one console event to the live log.
     *
     * @param  array<string, mixed>  $event
     */
    public function appendEvent(array $event): void
    {
        File::ensureDirectoryExists($this->directory());

        file_put_contents(
            $this->logPath(),
            json_encode($event, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n",
            FILE_APPEND,
        );
    }

    /**
     * Console events written after the given byte offset, with the offset to
     * resume from next time.
     *
     * Byte offsets (rather than event counts) let a client that has been
     * away — a reload, a tab opened halfway through — resume from exactly
     * where it stopped reading, and let a fresh client replay the run from
     * the beginning by asking for offset 0.
     *
     * @return array{offset: int, events: array<int, array<string, mixed>>}
     */
    public function eventsSince(int $offset): array
    {
        if (! File::exists($this->logPath())) {
            return ['offset' => 0, 'events' => []];
        }

        $size = filesize($this->logPath());

        // A new run truncates the log, so an offset past the end belongs to
        // a previous run — rewind rather than starve the client of output.
        if ($offset > $size) {
            $offset = 0;
        }

        $handle = fopen($this->logPath(), 'rb');

        if ($handle === false) {
            return ['offset' => $offset, 'events' => []];
        }

        fseek($handle, $offset);

        $events = [];
        $consumed = $offset;

        // Only whole lines are consumed: the last line may still be being
        // written, and its bytes must be re-read on the next poll.
        while (($line = fgets($handle)) !== false) {
            if (! str_ends_with($line, "\n")) {
                break;
            }

            $consumed += strlen($line);
            $event = json_decode(trim($line), true);

            if (is_array($event)) {
                $events[] = $event;
            }
        }

        fclose($handle);

        return ['offset' => $consumed, 'events' => $events];
    }

    /**
     * The full console log grouped by stage, as the run snapshot stores it.
     *
     * @return array<string, array<int, string>>
     */
    public function logsByStage(): array
    {
        $logs = [];

        foreach ($this->eventsSince(0)['events'] as $event) {
            if (! in_array($event['type'] ?? null, ['out', 'err'], true)) {
                continue;
            }

            $stage = $event['stage'] ?? null;
            $output = $event['output'] ?? '';

            if ($stage === null || $output === '') {
                continue;
            }

            $logs[$stage][] = $output;
        }

        return $logs;
    }

    public function directory(): string
    {
        return config('benchmark.run_path');
    }

    public function statePath(): string
    {
        return $this->directory().'/run.json';
    }

    public function logPath(): string
    {
        return $this->directory().'/run.log';
    }

    public function cancelPath(): string
    {
        return $this->directory().'/cancel';
    }

    /**
     * @param  array<int, string>  $stages
     * @return array<string, array<string, mixed>>
     */
    protected function initialStages(array $stages): array
    {
        $initial = [];

        foreach (CreateRunSnapshot::STAGES as $stage) {
            $initial[$stage] = [
                'status' => in_array($stage, $stages, true) ? 'pending' : 'skipped',
                'started_at' => null,
                'ended_at' => null,
            ];
        }

        return $initial;
    }

    /**
     * A run with no PID yet is given a grace period to boot; after that,
     * liveness is the PID itself.
     *
     * @param  array<string, mixed>  $run
     */
    protected function ownerIsAlive(array $run): bool
    {
        if ($run['pid'] === null) {
            return strtotime($run['started_at']) > time() - 30;
        }

        return $this->processIsAlive((int) $run['pid']);
    }

    /**
     * Signal 0 performs the permission and existence checks without
     * delivering anything, which is the cheapest liveness test available.
     * /proc covers builds without ext-posix.
     */
    protected function processIsAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return File::exists("/proc/{$pid}");
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(): ?array
    {
        if (! File::exists($this->statePath())) {
            return null;
        }

        $run = json_decode(File::get($this->statePath()), true);

        return is_array($run) ? $run : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function merge(array $attributes): array
    {
        return $this->write([...($this->read() ?? []), ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    protected function write(array $run): array
    {
        File::ensureDirectoryExists($this->directory());
        File::put($this->statePath(), json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $run;
    }
}
