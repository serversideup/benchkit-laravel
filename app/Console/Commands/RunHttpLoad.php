<?php

namespace App\Console\Commands;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadSizing;
use App\Support\Http\LoadStep;
use App\Support\Http\StepResult;
use App\Support\HttpBenchCommand;
use App\Support\RunState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Drives the web server load test on this machine.
 *
 * It is a command rather than a shell chain because the load has a step in the
 * middle that has to be computed: the response-time pass offers a rate derived
 * from what the sweep proved the server can hold, so the second half of the
 * stage cannot be written down until the first half has run. An external run
 * reaches the same two halves a different way — the generator is handed the
 * sweep, and the waiting command arms the response-time pass once it lands —
 * but both build their steps from the same HttpBenchCommand, so the two modes
 * measure the same thing.
 */
class RunHttpLoad extends Command
{
    protected $signature = 'benchmark:http-load';

    protected $description = 'Run the web server load test against this machine';

    public function handle(): int
    {
        $results = new HttpBenchmarkResults;
        $meta = $results->readMeta();

        if ($meta === null) {
            $this->error('No load settings were written for this run.');

            return self::FAILURE;
        }

        $profile = LoadProfile::fromMeta($meta);
        $command = new HttpBenchCommand;
        $insecure = $command->isInsecure($profile);

        // Deliberately does not announce levels: they are not known yet. The
        // probe measures one connection first, and the sweep is sized from
        // what it finds.
        $this->line(sprintf(
            '  Measuring one connection per route first%s',
            $profile->workers === null ? '' : sprintf(' — this server runs %d requests at once', $profile->workers)
        ));
        $this->newLine();

        // A self-test drives its own load, so the transport round trip is
        // whatever loopback costs — near enough to nothing that the probe
        // measures the server directly.
        $transportRtt = $meta['generator']['transport_rtt_ms'] ?? null;
        $this->connectTo = $profile->connectTo();

        if (! $this->runSteps($command, $command->probe($profile), $insecure)) {
            return self::FAILURE;
        }

        $levels = (new LoadSizing($results))->fromProbe($profile, $transportRtt);

        $this->newLine();
        $this->line(sprintf('  Sweeping at %s', $this->describe($levels)));
        $this->newLine();

        if (! $this->runSteps($command, $command->sweep($profile, $levels), $insecure)) {
            return self::FAILURE;
        }

        $curves = $this->curves($results, $levels);

        $this->newLine();

        return $this->runSteps($command, $command->latency($profile, $curves), $insecure)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Run a batch, reporting each measured window as it lands.
     *
     * @param  array<int, LoadStep>  $steps
     */
    protected function runSteps(HttpBenchCommand $command, array $steps, bool $insecure): bool
    {
        foreach ($steps as $step) {
            if ($this->cancelled()) {
                return false;
            }

            $result = $this->measure($command, $step, $insecure);

            if ($step->isMeasured()) {
                $this->report($step, $result);
            }

            sleep(HttpBenchCommand::SETTLE_SECONDS);
        }

        return true;
    }

    /**
     * @param  array<string, array<int, int>>  $levels
     * @return array<string, LoadCurve>
     */
    protected function curves(HttpBenchmarkResults $results, array $levels): array
    {
        $curves = [];

        foreach ($levels as $route => $routeLevels) {
            $curve = $results->curveFor($route, $routeLevels);

            if ($curve !== null) {
                $curves[$route] = $curve;
            }
        }

        return $curves;
    }

    /**
     * @param  array<string, array<int, int>>  $levels
     */
    protected function describe(array $levels): string
    {
        $unique = array_values(array_unique(array_map(
            fn (array $routeLevels): string => implode(', ', $routeLevels),
            $levels
        )));

        return count($unique) === 1
            ? $unique[0].' concurrent'
            : 'levels sized per route from what one connection measured';
    }

    /** @see LoadProfile::connectTo() */
    protected ?string $connectTo = null;

    protected function measure(HttpBenchCommand $command, LoadStep $step, bool $insecure): StepResult
    {
        $process = Process::fromShellCommandline($command->render($step, $insecure, null, $this->connectTo));
        // Generous next to the step's own window: oha is given a per-request
        // deadline of its own, so anything past this is the process itself
        // wedged rather than a slow server.
        $process->setTimeout($step->durationSeconds + 120)->run();

        if (! $process->isSuccessful()) {
            return StepResult::failed('the load generator did not finish');
        }

        if (! $step->isMeasured()) {
            return StepResult::skipped();
        }

        $output = $process->getOutput();
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return StepResult::failed('the load generator produced no readable result');
        }

        $path = $command->outputPath($step);

        if ($path !== null) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $output);
        }

        return StepResult::fromOha($decoded);
    }

    protected function report(LoadStep $step, StepResult $result): void
    {
        if (! $result->usable) {
            $this->line(sprintf('  %-30s %s', $step->label(), $result->failure ?? 'skipped'));

            return;
        }

        $this->line(sprintf(
            '  %-30s %12s req/s   p50 %9s   %s',
            $step->label(),
            number_format($result->requestsPerSecond, 1),
            $result->p50Ms === null ? '—' : number_format($result->p50Ms, 2).'ms',
            number_format($result->successRate * 100, 1).'%'
        ));
    }

    /**
     * A cancelled run should stop within one step rather than finishing a
     * stage nobody is waiting for.
     */
    protected function cancelled(): bool
    {
        return (new RunState)->cancelRequested();
    }
}
