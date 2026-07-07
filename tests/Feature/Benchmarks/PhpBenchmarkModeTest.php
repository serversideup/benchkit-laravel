<?php

namespace Tests\Feature\Benchmarks;

use Tests\TestCase;

class PhpBenchmarkModeTest extends TestCase
{
    public function test_php_benchmark_accepts_quick_mode(): void
    {
        $response = $this->post('/php', ['mode' => 'quick']);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_php_benchmark_accepts_full_mode(): void
    {
        $response = $this->post('/php', ['mode' => 'full']);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_php_benchmark_defaults_to_full_mode_when_mode_is_omitted(): void
    {
        $response = $this->post('/php');

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_php_benchmark_mode_is_validated(): void
    {
        $this->postJson('/php', ['mode' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');
    }
}
