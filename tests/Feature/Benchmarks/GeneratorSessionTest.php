<?php

namespace Tests\Feature\Benchmarks;

use App\Support\GeneratorSession;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class GeneratorSessionTest extends TestCase
{
    use UsesFakeRunPath;

    public function test_minting_a_pairing_returns_a_sanitized_payload(): void
    {
        $response = $this->postJson('/run/generator', ['target_url' => 'https://bench.example.com'])
            ->assertCreated();

        $generator = $response->json('generator');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $generator['token']);
        $this->assertSame(GeneratorSession::STATUS_WAITING, $generator['status']);
        $this->assertSame('https://bench.example.com', $generator['target_url']);
        $this->assertNull($generator['handshake']);
        $this->assertSame([], $generator['received']);
    }

    public function test_a_configured_benchmark_url_outranks_the_browser_origin(): void
    {
        config(['benchmark.http.url' => 'https://public.example.net']);

        $this->postJson('/run/generator', ['target_url' => 'https://bench.example.com'])
            ->assertCreated()
            ->assertJsonPath('generator.target_url', 'https://public.example.net');
    }

    public function test_the_target_url_must_be_a_url(): void
    {
        $this->postJson('/run/generator', ['target_url' => 'not a url'])
            ->assertUnprocessable();
    }

    public function test_re_minting_rotates_the_token_when_no_run_holds_the_pairing(): void
    {
        $first = $this->postJson('/run/generator', ['target_url' => 'https://bench.example.com'])->json('generator.token');
        $second = $this->postJson('/run/generator', ['target_url' => 'https://bench.example.com'])->json('generator.token');

        $this->assertNotSame($first, $second);
    }

    public function test_a_pairing_bound_to_a_live_run_is_not_replaced(): void
    {
        $run = (new RunState)->start(['http' => true], ['http'], null);
        (new RunState)->claim(getmypid());

        $session = new GeneratorSession;
        $token = $session->create('https://bench.example.com', 'https://bench.example.com')['token'];
        $session->arm($run['id'], '# work');

        $this->postJson('/run/generator', ['target_url' => 'https://elsewhere.example.com'])
            ->assertCreated()
            ->assertJsonPath('generator.token', $token);
    }

    public function test_an_abandoned_pairing_expires(): void
    {
        $session = new GeneratorSession;
        $session->create('https://bench.example.com', 'https://bench.example.com');

        $stale = json_decode(File::get($session->path()), true);
        $stale['last_seen_at'] = now()->subHours(2)->toIso8601String();
        File::put($session->path(), json_encode($stale));

        $this->assertNull($session->current());
        $this->assertFalse(File::exists($session->path()));
    }

    public function test_generator_polls_keep_a_pairing_alive(): void
    {
        $session = new GeneratorSession;
        $session->create('https://bench.example.com', 'https://bench.example.com');

        $aging = json_decode(File::get($session->path()), true);
        $aging['last_seen_at'] = now()->subMinutes(59)->toIso8601String();
        File::put($session->path(), json_encode($aging));

        $session->touch();

        $this->assertNotNull($session->current());
    }

    public function test_the_run_log_poll_carries_the_pairing(): void
    {
        (new GeneratorSession)->create('https://bench.example.com', 'https://bench.example.com');

        $this->getJson('/run/log')
            ->assertOk()
            ->assertJsonPath('generator.status', GeneratorSession::STATUS_WAITING);
    }

    public function test_the_run_log_poll_reports_no_pairing_when_none_exists(): void
    {
        $this->getJson('/run/log')
            ->assertOk()
            ->assertJsonPath('generator', null);
    }

    /**
     * A finished pairing must not present as a live one: the start screen
     * would show "Generator connected" for a generator that already exited.
     */
    public function test_a_finished_pairing_reads_as_no_pairing(): void
    {
        $session = new GeneratorSession;
        $session->create('https://bench.example.com', 'https://bench.example.com');
        $session->finish(GeneratorSession::STATUS_DONE);

        $this->getJson('/run/log')
            ->assertOk()
            ->assertJsonPath('generator', null);
    }
}
