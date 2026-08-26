<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorScript;
use App\Support\GeneratorSession;
use App\Support\Http\LoadProfile;
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

        $profile = new LoadProfile('https://bench.example.com', 'external', 100, 4, 20, [20]);
        $command = new HttpBenchCommand;

        $work = (new GeneratorScript)->work(
            $command->probe($profile),
            $command->isInsecure($profile),
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
        $this->post('/bench/generator/'.str_repeat('0', 40).'/results/static-c20')->assertNotFound();
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

    public function test_both_scripts_only_color_a_terminal_that_wants_it(): void
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
        $this->assertStringContainsString('oha -z 3s -c 8 -t 30s --redirect 0 --insecure', $work);
        $this->assertStringContainsString('oha -z 6s -c 1 -t 30s --redirect 0 --insecure --no-tui --output-format json', $work);
        $this->assertStringContainsString('/bench/io?ms=100', $work);
        // Every route, in measurement order, each uploading under its own slot.
        $this->assertSame(
            ['static-c1', 'json-c1', 'db-read-c1', 'io-c1'],
            array_values(array_filter(array_map(
                fn (string $line) => preg_match("/^if upload '([a-z0-9-]+)'/", $line, $m) ? $m[1] : null,
                explode("\n", $work),
            ))),
        );

        $this->assertSame(GeneratorSession::STATUS_RUNNING, (new GeneratorSession)->current()['status']);
    }

    /**
     * The failure this guards: a window the generator could not measure used
     * to be indistinguishable from a slow one. It moved on, the server kept
     * waiting, and the run sat out its whole no-progress timeout for a result
     * nobody was going to send.
     */
    public function test_a_window_the_generator_could_not_measure_is_reported(): void
    {
        $session = $this->armed();

        $this->call('POST', "/bench/generator/{$session['token']}/failed/static-c1", server: ['CONTENT_TYPE' => 'text/plain'], content: "error: Too many open files\nsecond line")
            ->assertStatus(202);

        $failed = (new GeneratorSession)->current()['failed'];

        $this->assertArrayHasKey('static-c1', $failed);
        // The reason travels with it. "No output captured" is the symptom, and
        // the cause is only visible on the machine running the generator.
        $this->assertStringContainsString('Too many open files', $failed['static-c1']['reason']);
    }

    public function test_a_failure_reason_is_capped_and_stripped_of_control_characters(): void
    {
        $session = $this->armed();

        $this->call('POST', "/bench/generator/{$session['token']}/failed/static-c1", server: ['CONTENT_TYPE' => 'text/plain'], content: "bad\x00stuff".str_repeat('x', 500))
            ->assertStatus(202);

        $reason = (new GeneratorSession)->current()['failed']['static-c1']['reason'];

        $this->assertLessThanOrEqual(200, mb_strlen($reason));
        $this->assertStringNotContainsString("\x00", $reason);
    }

    public function test_a_failure_report_for_an_unknown_window_is_refused(): void
    {
        $session = $this->armed();

        $this->post("/bench/generator/{$session['token']}/failed/etc-passwd")->assertNotFound();
    }

    /**
     * A generator that cannot hold the concurrency the sweep asks for does not
     * fail slowly: the connections that cannot open are counted as replies. It
     * reports what it can hold so the server can size the sweep to fit.
     */
    public function test_the_handshake_records_how_many_connections_the_generator_can_hold(): void
    {
        $session = $this->pair();

        $this->postJson("/bench/generator/{$session['token']}/handshake", [
            'oha_version' => '1.14.0',
            'cores' => 10,
            'host' => 'workstation-2.local',
            'rtt_ms' => 12.34,
            'fd_limit' => 256,
        ])->assertSuccessful();

        $this->assertSame(256, (new GeneratorSession)->current()['handshake']['fd_limit']);
    }

    /**
     * A run hands out two batches, and the second cannot be written down until
     * the first has landed. Between the two the generator keeps polling, and
     * the answer has to be "nothing new yet" rather than the batch it just
     * finished — otherwise it re-runs every window it has already uploaded and
     * the run never ends.
     */
    public function test_work_is_not_handed_out_twice(): void
    {
        $session = $this->armed();

        $this->get("/bench/generator/{$session['token']}/work")->assertOk();
        $this->get("/bench/generator/{$session['token']}/work")->assertNoContent();
    }

    public function test_a_second_batch_is_handed_out_once_the_server_arms_it(): void
    {
        $session = $this->armed();

        $this->get("/bench/generator/{$session['token']}/work")->assertOk();

        (new GeneratorSession)->rearm('# response times');

        $this->get("/bench/generator/{$session['token']}/work")
            ->assertOk()
            ->assertSee('# response times');
    }

    /**
     * Re-arming keeps what has already landed. Clearing it would erase the
     * record of the sweep the stage is counting, and the run would sit there
     * until it timed out on results it already had.
     */
    public function test_arming_a_second_batch_keeps_the_first_batch_results(): void
    {
        $session = $this->armed();

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
            ->assertCreated();

        (new GeneratorSession)->rearm('# response times');

        $this->assertSame(['static-c20'], array_keys((new GeneratorSession)->current()['received']));
    }

    /**
     * The bug this guards: the script ran its batch, printed "load test
     * complete", and exited while the server was still arming the second one.
     * The run then waited out its whole timeout for results nobody was coming
     * back for.
     */
    public function test_the_bootstrap_script_polls_again_after_finishing_a_batch(): void
    {
        $session = (new GeneratorSession)->create('https://bench.example.com', 'https://bench.example.com');

        $script = $this->get("/bench/generator/{$session['token']}/script")->assertOk()->getContent();

        $this->assertStringContainsString('BATCHES=$((BATCHES + 1))', $script);
        $this->assertStringContainsString('continue', $script);
        // Finishing only counts as success once a batch has actually run;
        // before that a retired pairing is a failure, not a clean exit.
        $this->assertStringContainsString('[ "$BATCHES" -gt 0 ]', $script);
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

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
            ->assertNotFound();

        $this->assertFileDoesNotExist((new HttpBenchmarkResults)->sweepPath('static', 20));
    }

    public function test_an_upload_lands_exactly_where_oha_would_have_written_it(): void
    {
        $session = $this->armed();

        $this->call(
            'POST',
            "/bench/generator/{$session['token']}/results/db-read-c20",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->ohaJson()),
        )->assertCreated()->assertJsonPath('requests_per_second', 1234.6);

        $this->assertFileExists((new HttpBenchmarkResults)->sweepPath('db_read', 20));
        $this->assertSame(['db-read-c20'], array_keys((new GeneratorSession)->current()['received']));
    }

    public function test_a_second_upload_for_the_same_route_is_rejected(): void
    {
        $session = $this->armed();
        $body = json_encode($this->ohaJson());

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertCreated();
        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertStatus(409);
    }

    public function test_uploads_without_measured_traffic_are_rejected_with_a_reason(): void
    {
        $session = $this->armed();

        $zeroBytes = json_encode($this->ohaJson(['summary' => ['requestsPerSec' => 175807.1, 'totalData' => 0]]));
        $response = $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: $zeroBytes);
        $response->assertStatus(422);
        $this->assertStringContainsString('zero bytes', $response->getContent());

        $noSuccesses = json_encode($this->ohaJson(['statusCodeDistribution' => ['502' => 12345]]));
        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: $noSuccesses)
            ->assertStatus(422);

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: 'not json')
            ->assertStatus(422);

        $rejections = (new GeneratorSession)->current()['rejections'];
        $this->assertCount(3, $rejections);
        $this->assertFileDoesNotExist((new HttpBenchmarkResults)->sweepPath('static', 20));
    }

    public function test_uploads_are_refused_before_the_run_arms(): void
    {
        $session = $this->pair();

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->ohaJson()))
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

        $this->call('POST', "/bench/generator/{$session['token']}/results/static-c20", server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat('x', 1_048_577))
            ->assertStatus(413);
    }
}
