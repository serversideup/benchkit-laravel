<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorScript;
use App\Support\GeneratorSession;
use App\Support\HttpBenchCommand;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class GeneratorEndpointsTest extends TestCase
{
    use UsesFakeResultsPath;
    use UsesFakeRunPath;

    protected function pair(): array
    {
        return (new GeneratorSession)->create('https://bench.example.com', 'https://bench.example.com');
    }

    /**
     * A paired session armed by a live run, which is the state uploads and
     * work fetches require.
     */
    protected function armed(): array
    {
        $session = $this->pair();
        $run = (new RunState)->start(['http' => true], ['http'], null);
        (new RunState)->claim(getmypid());

        $work = (new GeneratorScript)->work(
            (new HttpBenchCommand)->plan(['url' => 'https://bench.example.com', 'mode' => 'external'], 10, 50, 100),
            $session,
        );

        return (new GeneratorSession)->arm($run['id'], $work);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ohaJson(array $overrides = []): array
    {
        return array_merge([
            'summary' => ['successRate' => 1.0, 'requestsPerSec' => 1234.56, 'total' => 10.0, 'fastest' => 0.002, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 0.010, 'p95' => 0.025, 'p99' => 0.040],
            'statusCodeDistribution' => ['200' => 12345],
        ], $overrides);
    }

    public function test_every_endpoint_is_a_404_without_a_matching_token(): void
    {
        $this->pair();

        $this->get('/bench/generator/'.str_repeat('0', 40).'/script')->assertNotFound();
        $this->postJson('/bench/generator/'.str_repeat('0', 40).'/handshake')->assertNotFound();
        $this->get('/bench/generator/'.str_repeat('0', 40).'/work')->assertNotFound();
        $this->post('/bench/generator/'.str_repeat('0', 40).'/results/static')->assertNotFound();
    }

    public function test_the_endpoints_do_not_exist_without_a_pairing(): void
    {
        $this->get('/bench/generator/'.str_repeat('0', 40).'/script')->assertNotFound();
    }

    public function test_the_script_is_generated_for_the_pairing(): void
    {
        $session = $this->pair();

        $response = $this->get("/bench/generator/{$session['token']}/script")->assertOk();

        $script = $response->getContent();
        $this->assertStringContainsString($session['token'], $script);
        $this->assertStringContainsString("'https://bench.example.com'", $script);
        $this->assertStringContainsString('command -v oha', $script);
        $this->assertStringContainsString('BenchKit OK', $script);
        $this->assertStringContainsString('curl -k', $script);
    }

    public function test_the_script_refuses_to_install_oha_rather_than_doing_it_silently(): void
    {
        $session = $this->pair();

        $script = $this->get("/bench/generator/{$session['token']}/script")->assertOk()->getContent();

        $this->assertStringContainsString('command -v oha', $script);
        $this->assertStringContainsString('will not install it for you', $script);
        $this->assertStringContainsString('brew install oha', $script);
        $this->assertStringContainsString('releases/latest/download/', $script);
        // The download line names the asset for the machine running it, so
        // both architectures have to be resolvable.
        $this->assertStringContainsString('oha-linux-amd64', $script);
        $this->assertStringContainsString('oha-linux-arm64', $script);
        $this->assertStringContainsString('oha-macos-arm64', $script);
    }

    public function test_both_scripts_only_colour_a_terminal_that_wants_it(): void
    {
        $session = $this->pair();

        $script = $this->get("/bench/generator/{$session['token']}/script")->assertOk()->getContent();
        $work = $this->armed()['work'];

        // Piped to a log or a CI job, the output has to stay plain text: no
        // escape sequences, and no half-drawn line waiting to be rewritten.
        foreach (['bootstrap' => $script, 'work' => $work] as $name => $shell) {
            $this->assertStringContainsString('[ -t 1 ]', $shell, "{$name} does not check for a terminal");
            $this->assertStringContainsString('NO_COLOR', $shell, "{$name} ignores NO_COLOR");
            $this->assertStringContainsString('${TERM:-dumb}', $shell, "{$name} ignores TERM");
        }
    }

    public function test_the_handshake_records_the_generator_description(): void
    {
        $session = $this->pair();

        $this->postJson("/bench/generator/{$session['token']}/handshake", [
            'oha_version' => '1.4.5',
            'cores' => 8,
            'host' => 'generator-box',
            'rtt_ms' => 1.83,
        ])->assertOk()->assertJsonPath('status', 'connected');

        $current = (new GeneratorSession)->current();
        $this->assertSame(GeneratorSession::STATUS_CONNECTED, $current['status']);
        $this->assertSame('1.4.5', $current['handshake']['oha_version']);
        $this->assertSame(8, $current['handshake']['cores']);
        $this->assertSame(1.83, $current['handshake']['rtt_ms']);
        $this->assertNotNull($current['handshake']['source_ip']);
    }

    public function test_a_second_generator_cannot_handshake_once_the_run_is_armed(): void
    {
        $session = $this->armed();

        $this->postJson("/bench/generator/{$session['token']}/handshake", ['oha_version' => '1.4.5'])
            ->assertStatus(409);
    }

    public function test_work_is_204_while_the_run_has_not_reached_the_http_stage(): void
    {
        $session = $this->pair();

        $this->get("/bench/generator/{$session['token']}/work")->assertNoContent();
    }

    public function test_work_returns_the_standard_load_when_armed(): void
    {
        $session = $this->armed();

        $response = $this->get("/bench/generator/{$session['token']}/work")->assertOk();

        $work = $response->getContent();
        // The single-source guarantee: the fragment carries the same warmup,
        // flags, and route order the local chain is built from.
        $this->assertStringContainsString('oha -z 3s -c 50 --redirect 0 --insecure', $work);
        $this->assertStringContainsString('oha -z 10s -c 50 --redirect 0 --insecure --no-tui --output-format json', $work);
        $this->assertStringContainsString('/bench/io?ms=100', $work);
        $this->assertSame(
            ['static', 'json', 'db-read', 'io'],
            array_values(array_filter(array_map(
                fn (string $line) => preg_match("/^if upload '([a-z-]+)'/", $line, $m) ? $m[1] : null,
                explode("\n", $work),
            ))),
        );

        $this->assertSame(GeneratorSession::STATUS_RUNNING, (new GeneratorSession)->current()['status']);
    }

    /**
     * The pairing is retired the moment its run stops, so the poll answers as
     * it does for any token that no longer names a pairing. The script stops
     * on a 404 exactly as it does on a 410 — what matters is that it is told,
     * rather than left waiting on a run that will never fire.
     */
    public function test_work_stops_the_generator_when_the_armed_run_has_died(): void
    {
        $session = $this->armed();

        // The owning process "dies": a bogus PID fails the liveness check and
        // the run reconciles to interrupted.
        $run = json_decode(File::get(config('benchmark.run_path').'/run.json'), true);
        $run['pid'] = 999999999;
        File::put(config('benchmark.run_path').'/run.json', json_encode($run));

        $this->get("/bench/generator/{$session['token']}/work")->assertNotFound();
    }

    public function test_an_upload_for_a_run_that_has_died_is_refused(): void
    {
        $session = $this->armed();

        $run = json_decode(File::get(config('benchmark.run_path').'/run.json'), true);
        $run['pid'] = 999999999;
        File::put(config('benchmark.run_path').'/run.json', json_encode($run));

        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
            ->assertNotFound();

        $this->assertFileDoesNotExist((new HttpBenchmarkResults)->routePath('static'));
    }

    public function test_an_upload_lands_exactly_where_oha_would_have_written_it(): void
    {
        $session = $this->armed();

        $this->call(
            'POST',
            "/bench/generator/{$session['token']}/results/db-read",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->ohaJson()),
        )->assertCreated()->assertJsonPath('requests_per_second', 1234.6);

        $this->assertFileExists((new HttpBenchmarkResults)->routePath('db_read'));
        $this->assertSame(['db_read'], array_keys((new GeneratorSession)->current()['received']));
    }

    public function test_a_second_upload_for_the_same_route_is_rejected(): void
    {
        $session = $this->armed();
        $body = json_encode($this->ohaJson());

        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertCreated();
        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertStatus(409);
    }

    public function test_uploads_without_measured_traffic_are_rejected_with_a_reason(): void
    {
        $session = $this->armed();

        $zeroBytes = json_encode($this->ohaJson(['summary' => ['requestsPerSec' => 175807.1, 'totalData' => 0]]));
        $response = $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: $zeroBytes);
        $response->assertStatus(422);
        $this->assertStringContainsString('zero bytes', $response->getContent());

        $noSuccesses = json_encode($this->ohaJson(['statusCodeDistribution' => ['502' => 12345]]));
        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: $noSuccesses)
            ->assertStatus(422);

        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: 'not json')
            ->assertStatus(422);

        $rejections = (new GeneratorSession)->current()['rejections'];
        $this->assertCount(3, $rejections);
        $this->assertFileDoesNotExist((new HttpBenchmarkResults)->routePath('static'));
    }

    public function test_uploads_are_refused_before_the_run_arms(): void
    {
        $session = $this->pair();

        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
            ->assertNotFound();
    }

    public function test_uploads_for_unknown_routes_do_not_exist(): void
    {
        $session = $this->armed();

        $this->call('POST', "/bench/generator/{$session['token']}/results/etc-passwd", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
            ->assertNotFound();
    }

    public function test_oversized_uploads_are_refused(): void
    {
        $session = $this->armed();

        $this->call('POST', "/bench/generator/{$session['token']}/results/static", server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat('x', 1_048_577))
            ->assertStatus(413);
    }
}
