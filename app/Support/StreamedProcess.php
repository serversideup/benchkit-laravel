<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

/**
 * Runs a benchmark command as a subprocess and streams its output to the
 * browser as Server-Sent Events, guarded by a cache lock so only one
 * benchmark can run at a time.
 */
class StreamedProcess
{
    public const LOCK_KEY = 'benchmark-run';

    public const HEARTBEAT_KEY = 'benchmark-run-heartbeat';

    protected const HEARTBEAT_SECONDS = 30;

    protected const HEARTBEAT_TTL_SECONDS = 90;

    protected ?string $collectOutputPath = null;

    /** @var array<int, string> */
    protected array $collectedOutput = [];

    protected ?Process $process = null;

    public function __construct(
        protected string $command,
        protected int $lockSeconds = 3600,
    ) {}

    /**
     * Persist every streamed stdout line (ANSI-stripped) to the given file
     * once the process completes.
     */
    public function collectOutputTo(string $path): static
    {
        $this->collectOutputPath = $path;

        return $this;
    }

    public function response(): Response
    {
        $lock = Cache::lock(self::LOCK_KEY, $this->lockSeconds);

        if (! $lock->get() && ! $this->reclaimAbandonedLock($lock)) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Another benchmark is already running. If a previous run was interrupted, the lock clears automatically within a couple of minutes.',
            ], 409);
        }

        $this->refreshHeartbeat();

        return response()->stream(function () use ($lock) {
            $this->execute($lock);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * A live run refreshes a heartbeat key while it streams. When the lock
     * is held but the heartbeat has expired, the run that owned it was
     * killed without cleanup (container restart, crash) — reclaim the lock
     * instead of refusing benchmarks until the lock TTL runs out.
     */
    protected function reclaimAbandonedLock(Lock $lock): bool
    {
        if (Cache::has(self::HEARTBEAT_KEY)) {
            return false;
        }

        Cache::lock(self::LOCK_KEY)->forceRelease();

        return $lock->get();
    }

    protected function refreshHeartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, time(), self::HEARTBEAT_TTL_SECONDS);
    }

    protected function execute(Lock $lock): void
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        // The finally block is the primary release; the shutdown function
        // covers FPM killing the script mid-stream on client disconnect,
        // where finally blocks never run. Under Octane, workers do not shut
        // down per request, so the shutdown callback may fire long after —
        // that is safe because release() only succeeds for the lock owner
        // and stopProcess() is a no-op once the subprocess has exited.
        register_shutdown_function(function () use ($lock) {
            $this->stopProcess();
            $lock->release();
        });

        try {
            echo ": stream start\n\n";
            @ob_flush();
            flush();

            // `exec` makes the shell replace itself so getPid() is the
            // `script` process and stop() signals it directly; `script`
            // forwards the signal to the benchmark it wraps.
            $this->process = Process::fromShellCommandline(
                sprintf('exec script -qe /dev/null -c %s', escapeshellarg($this->command)),
                base_path(),
                null,
                null,
                null
            );

            $this->process->start();

            $lastHeartbeat = time();
            $lastPing = time();

            while ($this->process->isRunning()) {
                $this->emitLines($this->process->getIncrementalOutput(), 'out');
                $this->emitLines($this->process->getIncrementalErrorOutput(), 'err');

                if (time() - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                    $this->emit([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => 'heartbeat',
                        'output' => 'Connection alive',
                    ]);
                    $this->refreshHeartbeat();
                    $lastHeartbeat = time();
                }

                // An SSE comment forces a write so a cancelled client is
                // noticed within a second even when the benchmark is silent.
                // A cancelled run returns without persisting output or
                // emitting a status frame; finally still cleans up the lock.
                if (time() - $lastPing >= 1) {
                    echo ": ping\n\n";
                    @ob_flush();
                    flush();
                    $lastPing = time();

                    if (connection_aborted()) {
                        $this->stopProcess();

                        return;
                    }
                }

                usleep(100000);
            }

            $this->emitLines($this->process->getIncrementalOutput(), 'out');
            $this->emitLines($this->process->getIncrementalErrorOutput(), 'err');

            $this->persistCollectedOutput();

            $this->emit([
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => $this->process->isSuccessful() ? 'completed' : 'error',
                'error' => $this->process->isSuccessful() ? null : $this->process->getExitCodeText(),
            ]);
        } finally {
            Cache::forget(self::HEARTBEAT_KEY);
            $lock->release();
        }
    }

    /**
     * SIGTERM the wrapped `script` process, escalating to SIGKILL after
     * five seconds. Killing `script` also tears down the benchmark running
     * inside its pseudo-TTY.
     */
    protected function stopProcess(): void
    {
        if ($this->process?->isRunning()) {
            $this->process->stop(5);
        }
    }

    protected function emitLines(string $output, string $type): void
    {
        if ($output === '') {
            return;
        }

        foreach (explode("\n", trim($output)) as $line) {
            $text = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $line);
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

            if (trim($text) === '') {
                continue;
            }

            if ($type === 'out' && $this->collectOutputPath !== null) {
                $this->collectedOutput[] = $text;
            }

            $this->emit([
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => $type,
                'output' => $text,
            ]);
        }
    }

    /**
     * @param  array{timestamp: string, type?: string, status?: string, output?: string, error?: string|null}  $data
     */
    protected function emit(array $data): void
    {
        echo 'data: '.json_encode($data)."\n\n";
        @ob_flush();
        flush();
    }

    protected function persistCollectedOutput(): void
    {
        if ($this->collectOutputPath === null || $this->collectedOutput === []) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->collectOutputPath));
        File::put($this->collectOutputPath, implode("\n", $this->collectedOutput)."\n");
    }
}
