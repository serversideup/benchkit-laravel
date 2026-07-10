<?php

namespace Tests\Feature\Benchmarks;

use App\Support\StreamedProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpBenchmarkTest extends TestCase
{
    protected string $resultsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resultsPath = sys_get_temp_dir().'/benchkit-results-'.uniqid();
        File::ensureDirectoryExists($this->resultsPath);
        config(['benchmark.results_path' => $this->resultsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->resultsPath);

        parent::tearDown();
    }

    public function test_http_benchmark_streams_and_records_the_resolved_target(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $response = $this->post('/http');

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));

        $meta = json_decode(file_get_contents($this->resultsPath.'/http-meta.json'), true);
        $this->assertSame('http://localhost:8080', $meta['target']);
        $this->assertSame('loopback', $meta['mode']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_http_benchmark_uses_requested_load_settings(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->post('/http', ['duration' => 30, 'connections' => 100])->assertOk();

        $meta = json_decode(file_get_contents($this->resultsPath.'/http-meta.json'), true);
        $this->assertSame(30, $meta['duration_seconds']);
        $this->assertSame(100, $meta['connections']);
    }

    public function test_http_benchmark_falls_back_to_the_standard_load(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->post('/http')->assertOk();

        $meta = json_decode(file_get_contents($this->resultsPath.'/http-meta.json'), true);
        $this->assertSame(config('benchmark.http.duration_seconds'), $meta['duration_seconds']);
        $this->assertSame(config('benchmark.http.connections'), $meta['connections']);
    }

    public function test_http_benchmark_rejects_out_of_bounds_load_settings(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);

        $this->postJson('/http', ['duration' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration');

        $this->postJson('/http', ['duration' => 61])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration');

        $this->postJson('/http', ['connections' => 501])
            ->assertStatus(422)
            ->assertJsonValidationErrors('connections');
    }

    public function test_http_benchmark_rejects_redirecting_targets(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://localhost:8443'])]);

        $this->post('/http')
            ->assertStatus(503)
            ->assertJson(['status' => 'unreachable']);
    }

    public function test_http_benchmark_respects_the_run_lock(): void
    {
        Http::fake(['*' => Http::response('BenchKit OK', 200)]);
        Cache::lock(StreamedProcess::LOCK_KEY, 60)->get();
        Cache::put(StreamedProcess::HEARTBEAT_KEY, time(), 90);

        $this->post('/http')->assertStatus(409);
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
}
