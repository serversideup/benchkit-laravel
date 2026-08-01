<?php

namespace Tests\Feature\Benchmarks;

use App\Support\BenchmarkStages;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

class HttpBenchmarkTest extends TestCase
{
    use UsesFakeResultsPath;

    /**
     * Resolving the stage is what picks a reachable target and records the
     * load it will be tested with; the run process does this immediately
     * before launching the load generator.
     *
     * @param  array<string, mixed>  $settings
     * @return array{command: string, collect: ?string}
     */
    protected function resolveHttpStage(array $settings = []): array
    {
        return (new BenchmarkStages)->resolve('http', $settings);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return json_decode(file_get_contents($this->resultsPath.'/http-meta.json'), true);
    }

    public function test_the_http_stage_records_the_resolved_target(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame('http://localhost:8080', $meta['target']);
        $this->assertSame('loopback', $meta['mode']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_the_http_stage_uses_requested_load_settings(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage(['http_duration' => 30, 'http_connections' => 100]);

        $meta = $this->meta();
        $this->assertSame(30, $meta['duration_seconds']);
        $this->assertSame(100, $meta['connections']);
    }

    public function test_the_http_stage_falls_back_to_the_standard_load(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame(config('benchmark.http.duration_seconds'), $meta['duration_seconds']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_the_http_stage_rejects_redirecting_targets(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://localhost:8443'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not reach itself over HTTP');

        $this->resolveHttpStage();
    }

    public function test_the_http_stage_skips_targets_that_answer_200_without_the_sentinel_body(): void
    {
        Http::fake([
            'http://localhost:8080/*' => Http::response('', 200),
            'https://localhost:8443/*' => Http::response('BenchKit OK', 200),
        ]);

        $this->resolveHttpStage();

        $meta = $this->meta();
        $this->assertSame('https://localhost:8443', $meta['target']);
        $this->assertSame('loopback', $meta['mode']);
    }

    public function test_the_http_stage_fails_when_no_target_serves_the_sentinel_body(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->expectException(RuntimeException::class);

        $this->resolveHttpStage();
    }

    public function test_http_results_returns_no_results_when_no_run_has_happened(): void
    {
        $this->getJson('/http/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_http_summary_command_prints_detailed_metrics_from_oha_json(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => [
                'successRate' => 1.0,
                'requestsPerSec' => 5623.4,
                'total' => 10.0,
                'average' => 0.0089,
                'fastest' => 0.0012,
                'slowest' => 0.0234,
                'sizePerSec' => 12960890,
                'totalData' => 1804000,
            ],
            'latencyPercentiles' => ['p50' => 0.0085, 'p90' => 0.0121, 'p95' => 0.014, 'p99' => 0.018],
            'statusCodeDistribution' => ['200' => 56238],
            'errorDistribution' => ['aborted due to deadline' => 50],
        ]));

        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Requests/sec   5,623.4')
            ->expectsOutputToContain('p95 14.00')
            ->expectsOutputToContain('200: 56,238')
            ->expectsOutputToContain('aborted due to deadline: 50');
    }

    public function test_http_summary_command_is_quiet_when_a_route_has_no_results(): void
    {
        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static.');
    }

    public function test_http_summary_command_discards_runs_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => [
                'successRate' => 1.0,
                'requestsPerSec' => 175807.1,
                'total' => 10.0,
                'totalData' => 0,
            ],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1758717],
        ]));

        $this->artisan('benchmark:http-summary', ['route' => 'static'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No results were captured for static.');
    }

    public function test_http_results_parses_oha_output_per_route(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'duration_seconds' => 10,
            'connections' => 50,
        ]));

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56],
            'latencyPercentiles' => ['p50' => 0.010, 'p95' => 0.025, 'p99' => 0.040],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        File::put($this->resultsPath.'/http-db-read.json', json_encode([
            'summary' => ['successRate' => 0.99, 'requestsPerSec' => 456.78],
            'latencyPercentiles' => ['p50' => 0.050, 'p95' => 0.120, 'p99' => 0.300],
            'statusCodeDistribution' => ['200' => 4500, '500' => 45],
        ]));

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.mode', 'loopback');
        $response->assertJsonPath('http_results.target', 'http://localhost:8080');
        $response->assertJsonPath('http_results.routes.static.requests_per_second', 1234.6);
        $response->assertJsonPath('http_results.routes.static.p95_ms', 25);
        $response->assertJsonPath('http_results.routes.db_read.p99_ms', 300);
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }

    public function test_http_results_excludes_routes_that_transferred_zero_bytes(): void
    {
        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010],
            'statusCodeDistribution' => ['200' => 12345],
        ]));

        File::put($this->resultsPath.'/http-json.json', json_encode([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 155257.6, 'totalData' => 0],
            'latencyPercentiles' => ['p50' => 0.0001],
            'statusCodeDistribution' => ['200' => 1553175],
        ]));

        $response = $this->getJson('/http/results')->assertOk();

        $response->assertJsonPath('http_results.routes.static.requests_per_second', 1234.6);
        $this->assertArrayNotHasKey('json', $response->json('http_results.routes'));
    }
}
