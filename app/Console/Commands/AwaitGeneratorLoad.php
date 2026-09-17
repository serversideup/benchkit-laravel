<?php

namespace App\Console\Commands;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorScript;
use App\Support\GeneratorSession;
use App\Support\Http\GeneratorHandshake;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadSizing;
use App\Support\HttpBenchCommand;
use App\Support\HttpSummaryReport;
use App\Support\RunState;
use Illuminate\Console\Command;

/**
 * The HTTP stage of a run in external mode: instead of driving load, wait for
 * the paired generator to drive it and upload each route's result. Runs as an
 * ordinary stage command through BenchmarkProcess, so its output streams to
 * every watching browser, the 30-second heartbeat covers measured windows,
 * and cancellation kills it like any other stage.
 *
 * The deadline resets on every sign of progress — a handshake, the work being
 * fetched, each accepted upload — so it only expires on genuine abandonment.
 */
class AwaitGeneratorLoad extends Command
{
    protected $signature = 'benchmark:await-generator {--timeout=600 : Seconds without progress before giving up}';

    protected $description = 'Wait for a paired external generator to drive the HTTP load and upload its results';

    public function handle(GeneratorSession $session, RunState $state): int
    {
        $timeout = max(1, (int) $this->option('timeout'));
        $deadline = time() + $timeout;

        $announcedHandshake = false;
        $announcedRunning = false;
        $phase = 'probe';
        $lastPairingPrompt = 0;
        $reported = [];
        $rejectionsPrinted = 0;

        $awaiting = [];

        while (true) {
            $current = $session->current();

            if ($current === null) {
                $this->line('The generator pairing disappeared. It may have expired.');

                return self::FAILURE;
            }

            if ($awaiting === []) {
                $meta = (new HttpBenchmarkResults)->readMeta();

                if ($meta === null) {
                    $this->error('No load settings were written for this run.');

                    return self::FAILURE;
                }

                // The probe is one connection per route: the only measurement
                // that separates the server from the path to it.
                $awaiting = HttpBenchmarkResults::sweepSlots(array_fill_keys(
                    array_keys(HttpBenchmarkResults::ROUTES),
                    [LoadProfile::PROBE_CONCURRENCY],
                ));
            }

            // BenchmarkProcess kills this process on cancel, but polling the
            // flag too means the wait ends within a second either way.
            if ($state->cancelRequested()) {
                return self::FAILURE;
            }

            if (($current['handshake'] ?? null) !== null && ! $announcedHandshake) {
                $announcedHandshake = true;
                $deadline = time() + $timeout;
                $this->announceHandshake($current['handshake']);

                // The stage's meta was written before the generator
                // necessarily existed; fill in its description now.
                (new HttpBenchmarkResults)->mergeGeneratorMeta(
                    GeneratorHandshake::fromArray($current['handshake'])->toMeta()
                );
            }

            if (! $announcedHandshake && time() - $lastPairingPrompt >= 30) {
                $lastPairingPrompt = time();
                $this->printPairingPrompt($current);
            }

            if ($current['status'] === GeneratorSession::STATUS_RUNNING && ! $announcedRunning) {
                $announcedRunning = true;
                $deadline = time() + $timeout;
                $this->line('The generator picked up its work. Load starts now.');
            }

            $rejectionsPrinted = $this->printRejections($current, $rejectionsPrinted);

            foreach ($this->newlyReceived($current, $reported, $awaiting) as $slot) {
                $reported[] = $slot;
                $deadline = time() + $timeout;
                $this->printSlot($slot, $current['received'][$slot] ?? [], $current['failed'][$slot] ?? null);
            }

            if (count(array_diff($awaiting, $reported)) === 0) {
                $next = match ($phase) {
                    'probe' => $this->armSweep($session, $current),
                    'sweep' => $this->armLatency($session, $current),
                    default => [],
                };

                $phase = match ($phase) {
                    'probe' => 'sweep',
                    'sweep' => 'latency',
                    default => 'done',
                };

                if ($next !== []) {
                    $awaiting = array_merge($awaiting, $next);
                    $deadline = time() + $timeout;

                    continue;
                }

                if ($phase !== 'done') {
                    continue;
                }

                $session->finish(GeneratorSession::STATUS_DONE);
                $this->line('External load test complete.');

                return self::SUCCESS;
            }

            if (time() >= $deadline) {
                return $this->timeOut($session, count($reported), count($awaiting));
            }

            sleep(1);
        }
    }

    /**
     * Routes whose upload has landed and whose file parses. The session entry
     * is written after the file, so a listed route is a complete file — the
     * parse check is belt and braces against a torn read.
     *
     * @param  array<string, mixed>  $session
     * @param  array<int, string>  $reported
     * @return array<int, string>
     */
    protected function newlyReceived(array $session, array $reported, array $awaiting): array
    {
        $results = new HttpBenchmarkResults;
        $ready = [];

        foreach ($awaiting as $slot) {
            if (in_array($slot, $reported, true)) {
                continue;
            }

            // A window the generator gave up on counts as settled: it will
            // never arrive, and waiting for it only costs the run its timeout.
            // The curve is left with a gap, which is honest.
            if (array_key_exists($slot, $session['failed'] ?? [])) {
                $ready[] = $slot;

                continue;
            }

            $path = $results->pathForSlot($slot);

            if (array_key_exists($slot, $session['received'] ?? [])
                && $path !== null
                && $results->detail($slot, $path) !== null) {
                $ready[] = $slot;
            }
        }

        return $ready;
    }

    /**
     * Size the sweep from what one connection measured, and arm it.
     *
     * This is the batch that could not exist up front. A server answering in a
     * fraction of a millisecond behind a slow link needs far more connections
     * to reach its worker pool than the same server measured from its own
     * machine, and only the probe can tell the two apart.
     *
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    protected function armSweep(GeneratorSession $session, array $current): array
    {
        $results = new HttpBenchmarkResults;
        $profile = LoadProfile::fromMeta($results->readMeta() ?? []);
        $command = new HttpBenchCommand;
        $rtt = $current['handshake']['rtt_ms'] ?? null;
        $transportRtt = $current['handshake']['transport_rtt_ms'] ?? null;

        $levels = (new LoadSizing($results))->fromProbe($profile, $transportRtt);
        $steps = $command->sweep($profile, $levels);

        if ($steps === []) {
            return [];
        }

        $this->line(sprintf(
            'Probe complete. The server answers in %s behind a %sms network hop — sweeping to find its ceiling.',
            $this->serviceSummary($results, $transportRtt),
            $transportRtt ?? '?',
        ));

        $session->rearm((new GeneratorScript)->work($steps, $command->isInsecure($profile), $current, $profile->connectTo()));

        return array_map(
            fn ($step): string => HttpBenchmarkResults::slotFor($step->route, $step->phase, $step->connections),
            $steps
        );
    }

    /**
     * The fastest route's service time, which is the one the network swamps
     * first and therefore the one worth quoting.
     */
    protected function serviceSummary(HttpBenchmarkResults $results, ?float $transportRttMs): string
    {
        $times = array_filter(array_map(
            fn (string $route): ?float => $results->probeServiceMs($route, $transportRttMs),
            array_keys(HttpBenchmarkResults::ROUTES)
        ));

        return $times === [] ? 'an unknown time' : number_format(min($times), 2).'ms';
    }

    /**
     * Arm the response-time pass from what the sweep measured.
     *
     * Returns the slots it is now waiting on, or an empty array when no route
     * earned one — every route failed, which is a finished run with nothing
     * left to time rather than an error.
     *
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    protected function armLatency(GeneratorSession $session, array $current): array
    {
        $results = new HttpBenchmarkResults;
        $meta = $results->readMeta() ?? [];
        $profile = LoadProfile::fromMeta($meta);
        $command = new HttpBenchCommand;

        $curves = [];

        foreach ($meta['levels'] ?? [] as $key => $routeLevels) {
            $curve = $results->curveFor($key, array_map('intval', (array) $routeLevels));

            if ($curve !== null) {
                $curves[$key] = $curve;
            }
        }

        $steps = $command->latency($profile, $curves);

        if ($steps === []) {
            return [];
        }

        $this->line('Sweep complete. Measuring response times at a rate this server can hold.');

        $session->rearm((new GeneratorScript)->work($steps, $command->isInsecure($profile), $current, $profile->connectTo()));

        return array_map(
            fn ($step): string => HttpBenchmarkResults::slotFor($step->route, $step->phase, $step->connections),
            $steps
        );
    }

    /**
     * @param  array<string, mixed>  $handshake
     */
    protected function announceHandshake(array $handshake): void
    {
        $parts = array_filter([
            $handshake['host'] ?? null,
            isset($handshake['oha_version']) ? 'oha '.$handshake['oha_version'] : null,
            isset($handshake['cores']) ? $handshake['cores'].' cores' : null,
            isset($handshake['rtt_ms']) ? 'RTT '.$handshake['rtt_ms'].'ms' : null,
        ]);

        $from = isset($handshake['source_ip']) ? ' from '.$handshake['source_ip'] : '';

        $this->line(sprintf('Generator connected%s: %s.', $from, implode(' · ', $parts) ?: 'no details reported'));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function printPairingPrompt(array $session): void
    {
        $this->line('Waiting for an external load generator. On a machine near this server, run:');
        $this->line('');
        $this->line(sprintf('  curl -kfsSL %s/bench/generator/%s/script | sh', $session['base_url'], $session['token']));
        $this->line('');
        $this->line('That machine needs oha installed. The command installs nothing.');
        $this->line('Use a machine in the same datacenter or region. A distant one measures the network between them, not this server.');
    }

    /**
     * A sweep window gets one line, because the shape of the curve is what
     * matters across many of them. A response-time window gets the full block:
     * there is one per route, and they are what the results page publishes.
     *
     * @param  array<string, mixed>  $received
     */
    protected function printSlot(string $slot, array $received, ?array $failed = null): void
    {
        if ($failed !== null) {
            $this->line(sprintf('  %-24s %12s   %s', $slot, 'failed', $failed['reason'] ?? 'the generator captured no output'));

            return;
        }

        if ($received === []) {
            $this->line(sprintf('  %-24s %12s', $slot, 'no result'));

            return;
        }

        $rps = isset($received['requests_per_second']) ? number_format((float) $received['requests_per_second'], 1) : '?';
        $from = isset($received['source_ip']) ? ' from '.$received['source_ip'] : '';

        $this->line(sprintf('  %-24s %12s req/s%s', $slot, $rps, $from));

        if (! str_ends_with($slot, '-latency')) {
            return;
        }

        $results = new HttpBenchmarkResults;
        $path = $results->pathForSlot($slot);
        $detail = $path === null ? null : $results->detail($slot, $path);

        if ($detail !== null) {
            foreach ((new HttpSummaryReport)->lines($detail) as $line) {
                $this->line($line);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function printRejections(array $session, int $alreadyPrinted): int
    {
        $rejections = $session['rejections'] ?? [];

        foreach (array_slice($rejections, $alreadyPrinted) as $rejection) {
            $this->line(sprintf(
                'Rejected an upload for %s%s: %s',
                $rejection['route'] ?? '?',
                isset($rejection['source_ip']) ? ' from '.$rejection['source_ip'] : '',
                $rejection['reason'] ?? 'no reason recorded',
            ));
        }

        return count($rejections);
    }

    /**
     * A partial stage keeps what arrived: the snapshot only records completed
     * stages, so exiting non-zero here would silently discard uploaded
     * results. The routes map is self-describing — everything downstream
     * renders only the routes that are present.
     */
    protected function timeOut(GeneratorSession $session, int $received, int $expected): int
    {
        if ($received > 0) {
            $session->finish(GeneratorSession::STATUS_DONE);
            $this->line(sprintf(
                'The generator stopped after %d of %d measured windows. Keeping the partial results.',
                $received,
                $expected,
            ));

            return self::SUCCESS;
        }

        $session->finish(GeneratorSession::STATUS_ERROR);
        $this->line('No generator delivered results in time. The stage is marked failed; the rest of the run continues.');

        return self::FAILURE;
    }
}
