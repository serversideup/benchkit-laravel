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

    protected const HEARTBEAT_SECONDS = 30;

    protected ?string $collectOutputPath = null;

    /** @var array<int, string> */
    protected array $collectedOutput = [];

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

        if (! $lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Another benchmark is already running.',
            ], 409);
        }

        return response()->stream(function () use ($lock) {
            $this->execute($lock);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function execute(Lock $lock): void
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        set_time_limit(0);

        // The finally block is the primary release; the shutdown function
        // covers FPM killing the script mid-stream on client disconnect,
        // where finally blocks never run. Under Octane, workers do not shut
        // down per request, so the shutdown callback may fire long after —
        // that is safe because release() only succeeds for the lock owner.
        register_shutdown_function(function () use ($lock) {
            $lock->release();
        });

        try {
            echo ": stream start\n\n";
            @ob_flush();
            flush();

            $process = Process::fromShellCommandline(
                sprintf('script -qe /dev/null -c %s', escapeshellarg($this->command)),
                base_path(),
                null,
                null,
                null
            );

            $process->start();

            $lastHeartbeat = time();

            while ($process->isRunning()) {
                $this->emitLines($process->getIncrementalOutput(), 'out');
                $this->emitLines($process->getIncrementalErrorOutput(), 'err');

                if (time() - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                    $this->emit([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => 'heartbeat',
                        'output' => 'Connection alive',
                    ]);
                    $lastHeartbeat = time();
                }

                usleep(100000);
            }

            $this->emitLines($process->getIncrementalOutput(), 'out');
            $this->emitLines($process->getIncrementalErrorOutput(), 'err');

            $this->persistCollectedOutput();

            $this->emit([
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => $process->isSuccessful() ? 'completed' : 'error',
                'error' => $process->isSuccessful() ? null : $process->getExitCodeText(),
            ]);
        } finally {
            $lock->release();
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
