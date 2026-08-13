<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Specs\PhpSpecs;
use App\Support\BenchmarkHttpItems;
use Illuminate\Support\Facades\Schema;
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

    public function test_db_read_target_returns_rows_once_the_stage_has_prepared_them(): void
    {
        BenchmarkHttpItems::ensure();

        $response = $this->getJson('/bench/db-read');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(20, 'items');
    }

    /**
     * Preparing the table inside the request handler meant that under load
     * every request in flight raced to create and seed it, in the middle of the
     * measurement. A 503 lands in the status-code distribution instead, so a
     * run that hit this is visibly broken rather than quietly slow.
     */
    public function test_db_read_target_reports_unavailable_rather_than_seeding_under_load(): void
    {
        Schema::dropIfExists(BenchmarkHttpItems::TABLE);

        $response = $this->getJson('/bench/db-read');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'unavailable');
        $this->assertFalse(Schema::hasTable(BenchmarkHttpItems::TABLE), 'The request handler created the table it was supposed to refuse to create.');
    }

    public function test_io_target_defaults_to_100ms(): void
    {
        $response = $this->getJson('/bench/io');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('io_ms', 100);
    }

    public function test_io_target_respects_a_requested_delay(): void
    {
        $this->getJson('/bench/io?ms=0')->assertOk()->assertJsonPath('io_ms', 0);
    }

    public function test_io_target_clamps_the_delay_to_a_safe_band(): void
    {
        // A stray query string must never hang a worker; over/under the band
        // is clamped rather than honoured.
        $this->getJson('/bench/io?ms=5000')->assertJsonPath('io_ms', 1000);
        $this->getJson('/bench/io?ms=-50')->assertJsonPath('io_ms', 0);
    }

    public function test_targets_do_not_start_a_session(): void
    {
        $response = $this->get('/bench/static');

        $this->assertSame([], $response->headers->getCookies());
    }

    /**
     * The run document is assembled by the CLI, so this endpoint is the only
     * way it can learn what PHP looks like in the process that serves requests
     * — a different SAPI, with its own opcache, memory limit, and ini.
     */
    public function test_environment_target_reports_the_serving_process(): void
    {
        $response = $this->getJson('/bench/env');

        $response->assertOk();
        $response->assertJsonPath('php_version', PHP_VERSION);
        $response->assertJsonStructure(['php_version', 'php_server_api', 'octane', 'op_cache', 'memory_limit', 'ini']);
    }

    /**
     * A BenchKit instance is reachable by whoever the operator exposed it to,
     * so this endpoint publishes the same curated snapshot the results document
     * does — never a filesystem path.
     */
    public function test_environment_target_withholds_the_private_directives(): void
    {
        $response = $this->getJson('/bench/env');

        foreach (PhpSpecs::PRIVATE_INI_KEYS as $key) {
            $response->assertJsonMissingPath("ini.{$key}");
        }

        $this->assertNotEmpty(PhpSpecs::PRIVATE_INI_KEYS, 'The withheld-directive list is empty, so this test asserts nothing.');
    }

    public function test_environment_target_does_not_start_a_session(): void
    {
        $this->assertSame([], $this->get('/bench/env')->headers->getCookies());
    }
}
