<?php

namespace Tests\Feature\Benchmarks;

use App\Support\StreamedProcess;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StartBenchmarkTest extends TestCase
{
    public function test_yabs_benchmark_responds_with_a_server_sent_event_stream(): void
    {
        $response = $this->post('/yabs', [
            'disk' => true,
            'geekbench' => false,
            'iperf' => false,
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_only_one_benchmark_can_run_at_a_time(): void
    {
        Cache::lock(StreamedProcess::LOCK_KEY, 60)->get();

        $this->post('/php')
            ->assertStatus(409)
            ->assertJson(['status' => 'busy']);
    }

    public function test_starting_a_benchmark_holds_the_run_lock(): void
    {
        $this->post('/cfspeedtest', ['network_test_type' => 'ipv4'])->assertOk();

        $this->post('/yabs')->assertStatus(409);
    }

    public function test_geekbench_version_is_validated(): void
    {
        $this->postJson('/yabs', ['geekbench' => true, 'geekbench_version' => 7])
            ->assertStatus(422)
            ->assertJsonValidationErrors('geekbench_version');
    }

    public function test_network_test_type_is_validated(): void
    {
        $this->postJson('/cfspeedtest', ['network_test_type' => 'carrier-pigeon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('network_test_type');
    }

    public function test_a_failed_validation_does_not_hold_the_run_lock(): void
    {
        $this->postJson('/yabs', ['geekbench' => true, 'geekbench_version' => 7])->assertStatus(422);

        $this->assertTrue(Cache::lock(StreamedProcess::LOCK_KEY, 1)->get());
    }

    public function test_benchmarks_cannot_be_started_with_get(): void
    {
        $this->get('/yabs')->assertStatus(405);
        $this->get('/cfspeedtest')->assertStatus(405);
        $this->get('/php')->assertStatus(405);
    }
}
