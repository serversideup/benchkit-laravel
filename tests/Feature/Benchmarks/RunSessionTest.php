<?php

namespace Tests\Feature\Benchmarks;

use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class RunSessionTest extends TestCase
{
    use UsesFakeRunPath;

    protected function setUp(): void
    {
        parent::setUp();

        // The run is launched as a detached subprocess; faking the process
        // layer keeps these tests from starting real benchmarks.
        Process::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function settings(array $overrides = []): array
    {
        return [
            'hardware' => false,
            'disk' => false,
            'geekbench' => false,
            'iperf' => false,
            'network' => false,
            'http' => false,
            'php_database' => true,
            'php_mode' => 'quick',
            ...$overrides,
        ];
    }

    protected function state(): RunState
    {
        return app(RunState::class);
    }

    /**
     * Pretend the run is owned by a process that is still alive. PID 1
     * always exists, so it stands in for a healthy run.
     */
    protected function claimWithLivingProcess(): void
    {
        $path = $this->state()->statePath();
        $run = json_decode(File::get($path), true);
        File::put($path, json_encode([...$run, 'pid' => 1]));
    }

    public function test_starting_a_run_records_it_and_launches_the_detached_process(): void
    {
        $response = $this->postJson('/run', ['settings' => $this->settings(), 'preset' => 'quick']);

        $response->assertCreated()
            ->assertJsonPath('run.status', RunState::STATUS_RUNNING)
            ->assertJsonPath('run.stages.php.status', 'pending')
            ->assertJsonPath('run.stages.yabs.status', 'skipped');

        $this->assertTrue(File::exists($this->state()->statePath()));

        Process::assertRan(fn ($process) => str_contains($process->command, 'benchmark:run'));
    }

    public function test_only_one_run_can_be_active_at_a_time(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->postJson('/run', ['settings' => $this->settings()])
            ->assertStatus(409)
            ->assertJson(['status' => 'busy']);
    }

    /**
     * The refusal carries the live run so the second client can start
     * watching it rather than showing the user an error.
     */
    public function test_a_refused_start_returns_the_run_already_in_progress(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->postJson('/run', ['settings' => $this->settings()])
            ->assertStatus(409)
            ->assertJsonPath('run.status', RunState::STATUS_RUNNING);
    }

    public function test_a_run_whose_process_died_is_reported_as_interrupted(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();

        // A PID that cannot be running, and a start time past the grace
        // period a run gets to boot and claim itself.
        $path = $this->state()->statePath();
        $run = json_decode(File::get($path), true);
        File::put($path, json_encode([...$run, 'pid' => 999999999, 'started_at' => now()->subHour()->toIso8601String()]));

        $this->getJson('/run/log')
            ->assertOk()
            ->assertJsonPath('run.status', RunState::STATUS_INTERRUPTED);
    }

    public function test_a_new_run_can_start_once_the_previous_one_is_abandoned(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();

        $path = $this->state()->statePath();
        $run = json_decode(File::get($path), true);
        File::put($path, json_encode([...$run, 'pid' => 999999999]));

        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
    }

    public function test_the_console_log_replays_from_the_start_and_resumes_from_an_offset(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->state()->appendEvent(['stage' => 'php', 'type' => 'out', 'output' => 'first line']);
        $this->state()->appendEvent(['stage' => 'php', 'type' => 'out', 'output' => 'second line']);

        $replay = $this->getJson('/run/log?offset=0')->assertOk();
        $replay->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.output', 'first line');

        $offset = $replay->json('offset');

        $this->state()->appendEvent(['stage' => 'php', 'type' => 'out', 'output' => 'third line']);

        $this->getJson("/run/log?offset={$offset}")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.output', 'third line');
    }

    public function test_the_log_endpoint_reports_no_run_when_none_has_been_started(): void
    {
        $this->getJson('/run/log')
            ->assertOk()
            ->assertJsonPath('run', null)
            ->assertJsonCount(0, 'events');
    }

    public function test_any_client_can_cancel_the_run(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->postJson('/run/cancel')
            ->assertOk()
            ->assertJsonPath('cancel_requested', true);

        $this->assertTrue($this->state()->cancelRequested());
    }

    public function test_cancelling_without_a_run_is_a_not_found(): void
    {
        $this->postJson('/run/cancel')->assertStatus(404);
    }

    public function test_a_finished_run_can_be_dismissed(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->state()->finish(RunState::STATUS_COMPLETED, RunState::SAVE_EMPTY);

        $this->deleteJson('/run')->assertOk()->assertJsonPath('run', null);

        $this->assertFalse(File::exists($this->state()->statePath()));
    }

    public function test_a_live_run_cannot_be_dismissed(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->deleteJson('/run')->assertStatus(409)->assertJson(['status' => 'busy']);

        $this->assertTrue(File::exists($this->state()->statePath()));
    }

    public function test_a_run_with_no_stages_selected_is_rejected(): void
    {
        $this->postJson('/run', ['settings' => $this->settings([
            'php_database' => false,
        ])])->assertStatus(422)->assertJson(['status' => 'empty']);

        $this->assertFalse(File::exists($this->state()->statePath()));
    }

    public function test_geekbench_version_is_validated(): void
    {
        $this->postJson('/run', ['settings' => $this->settings([
            'hardware' => true,
            'geekbench' => true,
            'geekbench_version' => 7,
        ])])->assertStatus(422)->assertJsonValidationErrors('settings.geekbench_version');
    }

    public function test_network_test_type_is_validated(): void
    {
        $this->postJson('/run', ['settings' => $this->settings([
            'network' => true,
            'network_test_type' => 'carrier-pigeon',
        ])])->assertStatus(422)->assertJsonValidationErrors('settings.network_test_type');
    }

    public function test_http_load_settings_are_validated(): void
    {
        $this->postJson('/run', ['settings' => $this->settings([
            'http' => true,
            'http_duration' => 600,
            'http_connections' => 5000,
        ])])->assertStatus(422)
            ->assertJsonValidationErrors(['settings.http_duration', 'settings.http_connections']);
    }

    public function test_a_failed_validation_does_not_claim_the_run_session(): void
    {
        $this->postJson('/run', ['settings' => $this->settings(['php_mode' => 'sideways'])])
            ->assertStatus(422);

        $this->assertFalse($this->state()->isActive());
        Process::assertNothingRan();
    }

    public function test_a_run_in_progress_is_handed_to_a_freshly_loaded_page(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeRun.status', RunState::STATUS_RUNNING)
                ->etc());
    }

    /**
     * A run that finished cleanly is already in the run history, so a page
     * loaded afterwards starts fresh rather than reopening the console.
     */
    public function test_a_completed_run_is_not_handed_to_a_freshly_loaded_page(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->state()->finish(RunState::STATUS_COMPLETED, RunState::SAVE_SAVED, '20260101-120000-abcd');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeRun', null)->etc());
    }

    public function test_the_clear_run_command_forgets_an_abandoned_run(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();

        $path = $this->state()->statePath();
        $run = json_decode(File::get($path), true);
        File::put($path, json_encode([...$run, 'pid' => 999999999]));

        $this->artisan('benchmark:clear-run')->assertSuccessful();

        $this->assertFalse(File::exists($path));
    }

    public function test_the_clear_run_command_needs_forcing_for_a_live_run(): void
    {
        $this->postJson('/run', ['settings' => $this->settings()])->assertCreated();
        $this->claimWithLivingProcess();

        $this->artisan('benchmark:clear-run', ['--force' => true])->assertSuccessful();

        $this->assertFalse(File::exists($this->state()->statePath()));
    }

    public function test_benchmarks_cannot_be_started_with_get(): void
    {
        $this->get('/run')->assertStatus(405);
    }
}
