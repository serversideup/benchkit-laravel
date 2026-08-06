<?php

namespace Tests\Feature\Runs;

use App\Actions\Runs\SaveRunFromState;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

/**
 * The run process saves its own snapshot when it finishes, so a run that
 * completes while nobody is watching still lands in the run history.
 */
class SaveRunFromStateTest extends TestCase
{
    use UsesFakeResultsPath;
    use UsesFakeRunPath;

    protected RunState $state;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('runs');
        Http::preventStrayRequests();

        $this->state = app(RunState::class);
    }

    protected function writeHttpFixtures(): void
    {
        File::put($this->resultsPath.'/http-meta.json', json_encode([
            'target' => 'http://localhost:8080',
            'mode' => 'loopback',
            'duration_seconds' => 10,
            'connections' => 50,
        ]));

        File::put($this->resultsPath.'/http-static.json', json_encode([
            'summary' => ['requestsPerSec' => 34.1, 'successRate' => 1.0, 'totalData' => 1804000],
            'latencyPercentiles' => ['p50' => 1.84974, 'p95' => 2.33328, 'p99' => 2.39664],
            'statusCodeDistribution' => ['200' => 251],
        ]));
    }

    public function test_a_finished_run_is_written_to_the_run_history(): void
    {
        $this->writeHttpFixtures();

        $this->state->start(['http' => true], ['http'], 'custom');
        $this->state->startStage('http');
        $this->state->appendEvent(['stage' => 'http', 'type' => 'out', 'output' => 'Load testing /bench/static']);
        $this->state->finishStage('http', 'completed');

        $run = (new SaveRunFromState)->execute($this->state);

        $this->assertSame(RunState::SAVE_SAVED, $run['save_state']);
        $this->assertNotNull($run['snapshot_id']);

        Storage::disk('runs')->assertExists("{$run['snapshot_id']}.json");

        $snapshot = json_decode(Storage::disk('runs')->get("{$run['snapshot_id']}.json"), true);
        $this->assertSame(['http'], $snapshot['stages_completed']);
        $this->assertSame(['Load testing /bench/static'], $snapshot['logs']['http']);
    }

    /**
     * The console log is grouped by stage, and only the stages that
     * actually completed are kept — a failed stage's output would
     * otherwise be filed as though it were a result.
     */
    public function test_only_completed_stages_are_snapshotted(): void
    {
        $this->writeHttpFixtures();

        $this->state->start(['http' => true, 'php_database' => true], ['http', 'php'], 'custom');
        $this->state->finishStage('http', 'completed');
        $this->state->finishStage('php', 'error');
        $this->state->appendEvent(['stage' => 'php', 'type' => 'err', 'output' => 'phpbench blew up']);

        $run = (new SaveRunFromState)->execute($this->state);

        $snapshot = json_decode(Storage::disk('runs')->get("{$run['snapshot_id']}.json"), true);

        $this->assertSame(['http'], $snapshot['stages_completed']);
        $this->assertArrayNotHasKey('php', $snapshot['logs']);
        $this->assertNull($snapshot['benchmarks']['php']);
    }

    public function test_a_run_where_every_stage_failed_saves_nothing(): void
    {
        $this->state->start(['http' => true], ['http'], 'custom');
        $this->state->finishStage('http', 'error');

        $run = (new SaveRunFromState)->execute($this->state);

        $this->assertSame(RunState::SAVE_EMPTY, $run['save_state']);
        $this->assertNull($run['snapshot_id']);
        $this->assertSame([], Storage::disk('runs')->allFiles());
    }

    /**
     * Host details the user entered on a previous run are carried into the
     * next one, and outrank the network-derived guess.
     */
    public function test_remembered_host_details_are_carried_into_the_snapshot(): void
    {
        $this->writeHttpFixtures();

        $this->state->start(['http' => true], ['http'], 'custom', [
            'provider' => 'DigitalOcean',
            'plan' => 'Premium AMD 2GB',
            'datacenter' => 'NYC3',
            'cost' => ['amount' => 24, 'currency' => 'USD', 'period' => 'monthly'],
        ]);
        $this->state->finishStage('http', 'completed');

        $run = (new SaveRunFromState)->execute($this->state);
        $snapshot = json_decode(Storage::disk('runs')->get("{$run['snapshot_id']}.json"), true);

        $this->assertSame('DigitalOcean', $snapshot['meta']['provider']);
        $this->assertSame('user', $snapshot['meta']['provider_source']);
        $this->assertSame('NYC3', $snapshot['meta']['datacenter']);
        // assertEquals, not assertSame: JSON has a single number type, so a
        // whole amount comes back off disk as an int rather than a float.
        $this->assertEquals(['amount' => 24, 'currency' => 'USD', 'period' => 'monthly'], $snapshot['meta']['cost']);
    }

    /**
     * A browser that hasn't reloaded since cost became structured still
     * remembers "$24/mo" from localStorage and sends it on the next run. The
     * snapshot must never hold two shapes, so it's normalized on the way in.
     */
    public function test_a_free_text_cost_from_an_older_client_is_normalized(): void
    {
        $this->writeHttpFixtures();

        $this->state->start(['http' => true], ['http'], 'custom', [
            'provider' => 'Hetzner',
            'cost' => '20 EUR',
        ]);
        $this->state->finishStage('http', 'completed');

        $run = (new SaveRunFromState)->execute($this->state);
        $snapshot = json_decode(Storage::disk('runs')->get("{$run['snapshot_id']}.json"), true);

        $this->assertEquals(['amount' => 20, 'currency' => 'EUR', 'period' => 'monthly'], $snapshot['meta']['cost']);
    }
}
