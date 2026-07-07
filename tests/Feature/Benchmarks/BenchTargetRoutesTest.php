<?php

namespace Tests\Feature\Benchmarks;

use Tests\TestCase;

class BenchTargetRoutesTest extends TestCase
{
    public function test_static_target_responds(): void
    {
        $response = $this->get('/bench/static');

        $response->assertOk();
        $this->assertSame('BenchKit OK', $response->getContent());
    }

    public function test_json_target_returns_deterministic_payload(): void
    {
        $response = $this->getJson('/bench/json');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(25, 'items');
        $response->assertJsonPath('items.0.value', 7);
    }

    public function test_db_read_target_seeds_itself_and_returns_rows(): void
    {
        $response = $this->getJson('/bench/db-read');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(20, 'items');
    }

    public function test_targets_do_not_start_a_session(): void
    {
        $response = $this->get('/bench/static');

        $this->assertSame([], $response->headers->getCookies());
    }
}
