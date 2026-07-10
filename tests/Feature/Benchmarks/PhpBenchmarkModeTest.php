<?php

namespace Tests\Feature\Benchmarks;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhpBenchmarkModeTest extends TestCase
{
    /**
     * The mode-to-command mapping itself is covered by PhpBenchCommandTest;
     * this verifies the endpoint accepts every valid payload and streams.
     *
     * @param  array<string, string>  $payload
     */
    #[DataProvider('modes')]
    public function test_php_benchmark_streams_for_each_mode(array $payload): void
    {
        $response = $this->post('/php', $payload);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function modes(): array
    {
        return [
            'quick' => [['mode' => 'quick']],
            'full' => [['mode' => 'full']],
            'omitted (defaults to full)' => [[]],
        ];
    }

    public function test_php_benchmark_mode_is_validated(): void
    {
        $this->postJson('/php', ['mode' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');
    }
}
