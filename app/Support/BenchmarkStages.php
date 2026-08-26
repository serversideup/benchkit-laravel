<?php

namespace App\Support;

use App\Actions\Results\CloudflareSpeedTestResults;
use App\Actions\Results\HttpBenchmarkResults;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;
use App\Actions\Specs\WebRuntimeSpecs;
use App\Support\Http\LoadProfile;
use RuntimeException;

/**
 * Turns a run's settings into the stages to run and the command line each
 * one needs. This is the server-side twin of the `stages` map in
 * resources/js/Composables/useBenchmarkQueue.js — the two must agree on
 * which settings key enables which stage, so change them together.
 */
class BenchmarkStages
{
    /**
     * Stage order is fixed: hardware first (it is the longest and the one
     * users most want to see start), network before the HTTP self-test so a
     * saturated link is visible in the numbers, PHP last.
     */
    public const ORDER = ['yabs', 'cfspeedtest', 'http', 'php'];

    /**
     * All stages in the order this run should execute them.
     *
     * An external load test moves the HTTP stage to the front: it is the one
     * stage that needs a human and a second machine present, so it runs while
     * both still are. A generator that had to wait through fifteen minutes of
     * Geekbench is a generator whose laptop may have gone to sleep.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function order(array $settings): array
    {
        if ($this->bool($settings, 'http') && ($settings['http_generator'] ?? 'self') === 'external') {
            return ['http', ...array_values(array_diff(self::ORDER, ['http']))];
        }

        return self::ORDER;
    }

    /**
     * The stages the given settings ask for, in run order.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function enabled(array $settings): array
    {
        return array_values(array_filter($this->order($settings), fn (string $stage) => match ($stage) {
            'yabs' => $this->bool($settings, 'hardware'),
            'cfspeedtest' => $this->bool($settings, 'network'),
            'http' => $this->bool($settings, 'http'),
            'php' => $this->bool($settings, 'php_database'),
            default => false,
        }));
    }

    /**
     * The command for a stage, plus the file its console output should be
     * collected into (cfspeedtest publishes no machine-readable results, so
     * its results are parsed back out of what it printed).
     *
     * Preparation a stage needs before it can run happens here too, and
     * failing preparation throws — the caller records the stage as failed
     * and moves on rather than aborting the whole run.
     *
     * @param  array<string, mixed>  $settings
     * @return array{command: string, collect: ?string}
     *
     * @throws RuntimeException when the stage cannot run in this environment
     */
    public function resolve(string $stage, array $settings): array
    {
        return match ($stage) {
            'yabs' => [
                'command' => (new YabsCommand)->build([
                    'disk' => $this->bool($settings, 'disk'),
                    'geekbench' => $this->bool($settings, 'geekbench'),
                    'geekbench_version' => $settings['geekbench_version'] ?? 6,
                    'iperf' => $this->bool($settings, 'iperf'),
                ]),
                'collect' => null,
            ],
            'cfspeedtest' => [
                'command' => (new CfSpeedTestCommand)->build($settings['network_test_type'] ?? 'ipv4'),
                'collect' => (new CloudflareSpeedTestResults)->path(),
            ],
            'http' => $this->httpStage($settings),
            'php' => [
                'command' => (new PhpBenchCommand)->build($settings['php_mode'] ?? 'full'),
                'collect' => null,
            ],
            default => throw new RuntimeException("Unknown benchmark stage [{$stage}]."),
        };
    }

    /**
     * Human-readable stage names, used for the banner that separates stages
     * in the run's console log.
     */
    public function label(string $stage): string
    {
        return match ($stage) {
            'yabs' => 'Hardware',
            'cfspeedtest' => 'Network',
            'http' => 'Web server load',
            'php' => 'PHP',
            default => $stage,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{command: string, collect: ?string}
     */
    protected function httpStage(array $settings): array
    {
        $target = (new HttpBenchmarkTarget)->resolve();

        if ($target === null) {
            throw new RuntimeException('The application could not reach itself over HTTP. Set BENCHMARK_HTTP_URL to a URL this server can reach.');
        }

        BenchmarkHttpItems::ensure();

        // Ask the target what PHP looks like over there before loading it. This
        // is the only moment a run can see the serving process rather than the
        // CLI one assembling the results, and it has to happen while the target
        // is known-good and idle. The local target is right for this even in
        // external mode — the probe is the app asking itself, not load.
        $webRuntime = (new WebRuntimeSpecs)->capture($target['url']);
        $workers = $this->workerCeiling($webRuntime);

        // The load is sized from the machine rather than from a fixed number,
        // so one setting fits a one-core box and a thirty-two-core box. Both
        // ceilings are needed: the CPU routes bend near the core count and
        // /bench/io bends at the worker count, and a sweep seeded from only
        // one of them steps straight over the other.
        $profile = LoadProfile::fromSettings($target, $settings, $this->coreCount(), $workers);
        $results = new HttpBenchmarkResults;

        if (($settings['http_generator'] ?? 'self') === 'external') {
            return $this->externalHttpStage($results, $profile);
        }

        $results->writeMeta($target, $profile, $workers);

        return [
            'command' => sprintf(
                '%s %s benchmark:http-load',
                config('benchmark.php_binary'),
                escapeshellarg(base_path('artisan'))
            ),
            'collect' => null,
        ];
    }

    /**
     * External mode: instead of driving load, the stage arms the pairing with
     * work rendered from this run's settings and waits for the generator to
     * upload the four route results. Everything downstream is unchanged —
     * uploads land exactly where oha would have written them.
     *
     * @return array{command: string, collect: ?string}
     */
    protected function externalHttpStage(HttpBenchmarkResults $results, LoadProfile $profile): array
    {
        $session = (new GeneratorSession)->current();

        if ($session === null) {
            throw new RuntimeException('External load test selected but no generator is paired. Open BenchKit, choose "External load test", and run the pairing command on a second machine.');
        }

        // In external mode a result file's existence means "uploaded during
        // this run", so a previous run's files cannot be allowed to satisfy
        // the wait. The results directory deliberately survives runs.
        $results->clearRouteResults();

        // The generator hits the pairing's public origin, not the loopback
        // the self-test would use, so the profile is rebound to it.
        $target = ['url' => $session['target_url'], 'mode' => 'external'];
        $profile = $profile->against($target);

        $results->writeMeta($target, $profile, $profile->workers, $this->generatorMeta($session));

        $runId = (new RunState)->current()['id'] ?? '';
        $command = new HttpBenchCommand;

        // Only the probe is armed here. Each later batch depends on what the
        // one before it measured — the sweep's levels come from how long the
        // server took at one connection, and the response-time pass offers a
        // fraction of what the sweep proved it can hold — so neither can be
        // written down yet. The waiting command arms them in turn, and the
        // generator picks each up on the same poll it already uses.
        // The generator has not handshaked yet at this point, so there is no
        // address to pin the probe to. The batches that follow are armed after
        // it has, and carry one.
        (new GeneratorSession)->arm($runId, (new GeneratorScript)->work(
            $command->probe($profile),
            $command->isInsecure($profile),
            $session,
        ));

        return [
            'command' => sprintf('%s %s benchmark:await-generator', config('benchmark.php_binary'), escapeshellarg(base_path('artisan'))),
            'collect' => null,
        ];
    }

    /**
     * The generator block as far as it is known when the stage arms. The
     * handshake may not have happened yet; the waiting command merges the
     * details in when it does.
     *
     * @param  array<string, mixed>  $session
     * @return array{mode: string, rtt_ms: float|null, source_ip: string|null, oha_version: string|null, host: string|null}
     */
    protected function generatorMeta(array $session): array
    {
        $handshake = $session['handshake'] ?? null;

        return [
            'mode' => 'external',
            'rtt_ms' => $handshake['rtt_ms'] ?? null,
            'source_ip' => $handshake['source_ip'] ?? null,
            'oha_version' => $handshake['oha_version'] ?? null,
            'host' => $handshake['host'] ?? null,
        ];
    }

    /**
     * How many requests this server will process at once — an FPM pool size, a
     * FrankenPHP thread count, an Octane worker count — when the environment
     * exposes one. Recorded with the load settings so the results can flag a run
     * whose concurrency exceeded it, because past that point the throughput
     * figure describes the ceiling rather than the application.
     *
     * Taken from the serving process when it could be reached, because that is
     * the pool the load test is about to run against. The local probe is the
     * fallback: it reads the same pool file from the CLI, which is right often
     * enough to be worth having and is all that was ever available before.
     *
     * Null when nothing could be detected, which is a real answer on a managed
     * platform and never a fabricated default.
     *
     * @param  array<string, mixed>|null  $webRuntime
     */
    /**
     * Cores the machine reports, for sizing the sweep's lower levels.
     *
     * Null rather than a guess when it cannot be read: LoadProfile falls back
     * to a wide spread, which is honest, where a fabricated core count would
     * put the levels confidently in the wrong place.
     */
    protected function coreCount(): ?int
    {
        $cores = (new ServerSpecs)->execute()['cpu_cores'] ?? null;

        return is_numeric($cores) ? (int) $cores : null;
    }

    protected function workerCeiling(?array $webRuntime): ?int
    {
        $workers = $webRuntime['runtime']['workers']
            ?? (new PhpSpecs)->execute()['runtime']['workers']
            ?? null;

        return is_numeric($workers) ? (int) $workers : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function bool(array $settings, string $key): bool
    {
        return filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    }
}
