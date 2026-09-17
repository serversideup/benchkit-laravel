<?php

namespace Tests\Concerns;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\Http\LoadProfile;
use Illuminate\Support\Facades\File;

/**
 * Writes a results directory in the shape a finished web server load test
 * leaves behind: the settings it ran with, one file per concurrency level, and
 * one response-time pass per route.
 *
 * Shared because five test files used to build that fixture by hand, which
 * meant five places to update whenever the measurement changed — and the
 * measurement changing is exactly when a fixture is most likely to drift from
 * what the parser expects. Requires UsesFakeResultsPath.
 */
trait SeedsHttpResults
{
    /**
     * A curve that climbs and then flattens, like a real one.
     *
     * @param  array<int, int>  $levels
     */
    protected function seedHttpResults(
        array $levels = [1, 4, 20, 40],
        ?int $workers = 20,
        string $mode = 'loopback',
        bool $withLatency = true,
    ): void {
        $target = ['url' => 'http://localhost:8080', 'mode' => $mode];
        $profile = new LoadProfile($target['url'], $mode, 100, 4, $workers, $levels);

        (new HttpBenchmarkResults)->writeMeta($target, $profile, $workers);

        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $route) {
            foreach ($levels as $connections) {
                $this->writeOha(
                    (new HttpBenchmarkResults)->sweepPath($route, $connections),
                    $this->plateauRps($connections, $workers),
                    $connections,
                );
            }

            if ($withLatency) {
                $this->writeOha((new HttpBenchmarkResults)->latencyPath($route), 140.0, 4);
            }
        }
    }

    /**
     * Throughput that rises with concurrency until the worker count and then
     * stops, so a seeded curve has a knee to find.
     */
    protected function plateauRps(int $connections, ?int $workers): float
    {
        $ceiling = $workers ?? 20;

        return round(min($connections, $ceiling) * 10.0, 1);
    }

    /**
     * The oha document, including the deadline aborts every real run reports —
     * one per connection. Leaving them out of a fixture would hide the case
     * that once made every level look like a failure.
     *
     * $averageSeconds overrides the closed-loop identity the fixture otherwise
     * satisfies exactly. Without it no fixture can express a generator that
     * left connections idle, because rate x mean always lands on the
     * concurrency offered.
     *
     * @param  array<string|int, int>|null  $statusCodes
     */
    protected function writeOha(string $path, float $rps, int $connections, ?array $statusCodes = null, ?float $averageSeconds = null): void
    {
        $average = $averageSeconds ?? ($rps > 0 ? $connections / $rps : 0.0);
        $total = max(1, (int) round($rps * 6));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'summary' => [
                'successRate' => 1.0,
                'total' => 6.0,
                'slowest' => $average * 4,
                'fastest' => $average / 2,
                'average' => $average,
                'requestsPerSec' => $rps,
                'totalData' => $total * 31,
                'sizePerRequest' => 31,
                'sizePerSec' => $total * 31 / 6,
            ],
            'latencyPercentiles' => [
                'p50' => $average,
                'p90' => $average * 1.5,
                'p95' => $average * 2,
                'p99' => $average * 3,
            ],
            'statusCodeDistribution' => $statusCodes ?? ['200' => $total],
            'errorDistribution' => ['aborted due to deadline' => $connections],
        ]));
    }
}
