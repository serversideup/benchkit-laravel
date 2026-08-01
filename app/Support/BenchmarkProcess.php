<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Runs a benchmark command as a subprocess and hands every line of its
 * output to a callback.
 *
 * The benchmarks this wraps write progress bars and colour, and several
 * only produce them when they believe they are attached to a terminal, so
 * the command runs inside `script`'s pseudo-TTY.
 */
class BenchmarkProcess
{
    /**
     * How long the subject may be silent before the run emits a heartbeat.
     * Geekbench and the full phpbench suite both go minutes without a line,
     * and without this the console reads as hung.
     */
    protected const HEARTBEAT_SECONDS = 30;

    protected ?string $collectOutputPath = null;

    /** @var array<int, string> */
    protected array $collectedOutput = [];

    protected ?Process $process = null;

    /** @var (callable(): bool)|null */
    protected $cancelCheck = null;

    /**
     * @param  callable(array<string, mixed>): void  $onEvent
     */
    public function __construct(
        protected string $command,
        protected $onEvent,
    ) {}

    /**
     * Persist every stdout line (ANSI-stripped) to the given file once the
     * process completes. Used by stages whose results are parsed back out
     * of their console output rather than a machine-readable file.
     */
    public function collectOutputTo(string $path): static
    {
        $this->collectOutputPath = $path;

        return $this;
    }

    /**
     * Polled roughly once a second; returning true stops the subprocess.
     *
     * @param  callable(): bool  $check
     */
    public function cancelWhen(callable $check): static
    {
        $this->cancelCheck = $check;

        return $this;
    }

    /**
     * Run to completion, returning whether the subject exited successfully.
     * A cancelled run returns false without persisting collected output.
     */
    public function run(): bool
    {
        // `exec` makes the shell replace itself so getPid() is the `script`
        // process and stop() signals it directly; `script` forwards the
        // signal to the benchmark it wraps.
        $this->process = Process::fromShellCommandline(
            sprintf('exec script -qe /dev/null -c %s', escapeshellarg($this->command)),
            base_path(),
            null,
            null,
            null,
        );

        $this->process->start();

        $lastHeartbeat = time();
        $lastCancelCheck = time();

        while ($this->process->isRunning()) {
            $this->emitLines($this->process->getIncrementalOutput(), 'out');
            $this->emitLines($this->process->getIncrementalErrorOutput(), 'err');

            if (time() - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                $this->emit(['type' => 'heartbeat', 'output' => 'Connection alive']);
                $lastHeartbeat = time();
            }

            if (time() - $lastCancelCheck >= 1) {
                $lastCancelCheck = time();

                if ($this->cancelCheck !== null && ($this->cancelCheck)()) {
                    $this->stop();

                    return false;
                }
            }

            usleep(100000);
        }

        $this->emitLines($this->process->getIncrementalOutput(), 'out');
        $this->emitLines($this->process->getIncrementalErrorOutput(), 'err');

        $this->persistCollectedOutput();

        return $this->process->isSuccessful();
    }

    public function exitCodeText(): ?string
    {
        return $this->process?->getExitCodeText();
    }

    /**
     * SIGTERM the wrapped `script` process, escalating to SIGKILL after
     * five seconds. Killing `script` also tears down the benchmark running
     * inside its pseudo-TTY.
     */
    public function stop(): void
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

            $this->emit(['type' => $type, 'output' => $text]);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function emit(array $event): void
    {
        ($this->onEvent)([...$event, 'timestamp' => date('Y-m-d H:i:s')]);
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
