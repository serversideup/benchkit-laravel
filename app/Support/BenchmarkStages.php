<?php

namespace App\Support;

use App\Actions\Results\CloudflareSpeedTestResults;
use App\Actions\Results\HttpBenchmarkResults;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\WebRuntimeSpecs;
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
     * The stages the given settings ask for, in run order.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function enabled(array $settings): array
    {
        return array_values(array_filter(self::ORDER, fn (string $stage) => match ($stage) {
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

        $duration = (int) ($settings['http_duration'] ?? config('benchmark.http.duration_seconds'));
        $connections = (int) ($settings['http_connections'] ?? config('benchmark.http.connections'));
        $ioMs = (int) ($settings['http_io_ms'] ?? config('benchmark.http.io_ms'));

        BenchmarkHttpItems::ensure();

        // Ask the target what PHP looks like over there before loading it. This
        // is the only moment a run can see the serving process rather than the
        // CLI one assembling the results, and it has to happen while the target
        // is known-good and idle.
        $webRuntime = (new WebRuntimeSpecs)->capture($target['url']);

        (new HttpBenchmarkResults)->writeMeta($target, $duration, $connections, $ioMs, $this->workerCeiling($webRuntime));

        return [
            'command' => (new HttpBenchCommand)->build($target, $duration, $connections, $ioMs),
            'collect' => null,
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
