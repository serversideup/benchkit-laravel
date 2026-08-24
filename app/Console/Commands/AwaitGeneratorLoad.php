<?php

namespace App\Console\Commands;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorSession;
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
        $lastPairingPrompt = 0;
        $reported = [];
        $rejectionsPrinted = 0;

        while (true) {
            $current = $session->current();

            if ($current === null) {
                $this->line('The generator pairing disappeared — it may have expired.');

                return self::FAILURE;
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
                (new HttpBenchmarkResults)->mergeGeneratorMeta([
                    'rtt_ms' => $current['handshake']['rtt_ms'] ?? null,
                    'source_ip' => $current['handshake']['source_ip'] ?? null,
                    'oha_version' => $current['handshake']['oha_version'] ?? null,
                    'host' => $current['handshake']['host'] ?? null,
                ]);
            }

            if (! $announcedHandshake && time() - $lastPairingPrompt >= 30) {
                $lastPairingPrompt = time();
                $this->printPairingPrompt($current);
            }

            if ($current['status'] === GeneratorSession::STATUS_RUNNING && ! $announcedRunning) {
                $announcedRunning = true;
                $deadline = time() + $timeout;
                $this->line('The generator picked up its work — load starts now.');
            }

            $rejectionsPrinted = $this->printRejections($current, $rejectionsPrinted);

            foreach ($this->newlyReceived($current, $reported) as $key) {
                $reported[] = $key;
                $deadline = time() + $timeout;
                $this->printRoute($key, $current['received'][$key] ?? []);
            }

            if (count($reported) === count(HttpBenchmarkResults::ROUTES)) {
                $session->finish(GeneratorSession::STATUS_DONE);
                $this->line('External load test complete — all routes received.');

                return self::SUCCESS;
            }

            if (time() >= $deadline) {
                return $this->timeOut($session, count($reported));
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
    protected function newlyReceived(array $session, array $reported): array
    {
        $results = new HttpBenchmarkResults;
        $ready = [];

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $key) {
            if (! in_array($key, $reported, true)
                && array_key_exists($key, $session['received'] ?? [])
                && $results->detail($key) !== null) {
                $ready[] = $key;
            }
        }

        return $ready;
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

        $this->line(sprintf('Generator connected%s — %s.', $from, implode(' · ', $parts) ?: 'no details reported'));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function printPairingPrompt(array $session): void
    {
        $this->line('Waiting for an external load generator. On a machine near this server, run:');
        $this->line('');
        $this->line(sprintf('  curl -fsSL %s/bench/generator/%s/script | sh', $session['base_url'], $session['token']));
        $this->line('');
        $this->line('A machine in the same datacenter or region measures the server; a distant one measures the network between them.');
    }

    /**
     * @param  array<string, mixed>  $received
     */
    protected function printRoute(string $key, array $received): void
    {
        $path = HttpBenchmarkResults::ROUTES[$key];
        $from = isset($received['source_ip']) ? ' from '.$received['source_ip'] : '';
        $rps = isset($received['requests_per_second']) ? number_format((float) $received['requests_per_second'], 1) : '?';

        $this->line(sprintf('Received %s%s — %s req/s', $path, $from, $rps));

        $detail = (new HttpBenchmarkResults)->detail($key);

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
    protected function timeOut(GeneratorSession $session, int $received): int
    {
        if ($received > 0) {
            $session->finish(GeneratorSession::STATUS_DONE);
            $this->line(sprintf(
                'The generator stopped after %d of %d routes — keeping the partial results.',
                $received,
                count(HttpBenchmarkResults::ROUTES),
            ));

            return self::SUCCESS;
        }

        $session->finish(GeneratorSession::STATUS_ERROR);
        $this->line('No generator delivered results in time. The stage is marked failed; the rest of the run continues.');

        return self::FAILURE;
    }
}
