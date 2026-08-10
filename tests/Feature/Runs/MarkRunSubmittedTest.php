<?php

namespace Tests\Feature\Runs;

use App\Actions\Runs\FindRun;
use Tests\Concerns\SeedsRunSnapshots;
use Tests\TestCase;

class MarkRunSubmittedTest extends TestCase
{
    use SeedsRunSnapshots;

    public function test_a_fresh_run_carries_no_submission_marker(): void
    {
        $id = $this->seedRun('20260807-120000-aaaa');

        $this->assertArrayNotHasKey('submission_opened_at', (new FindRun)->execute($id));
    }

    public function test_opening_a_submission_records_it_on_the_run(): void
    {
        $id = $this->seedRun('20260807-120000-aaaa');

        $this->postJson("/runs/{$id}/submission")
            ->assertOk()
            ->assertJsonPath('run.id', $id);

        $this->assertNotNull((new FindRun)->execute($id)['submission_opened_at']);
    }

    public function test_the_marker_is_stored_on_the_run_so_every_client_sees_it(): void
    {
        $id = $this->seedRun('20260807-120000-aaaa');

        $this->postJson("/runs/{$id}/submission")->assertOk();

        // A run is owned by the server, so the same run opened in another tab
        // or on another device has to carry the same warning.
        $this->get("/runs/{$id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('run.submission_opened_at', (new FindRun)->execute($id)['submission_opened_at']));
    }

    public function test_reopening_a_submission_keeps_the_first_date(): void
    {
        $id = $this->seedRun('20260807-120000-aaaa');

        $first = $this->postJson("/runs/{$id}/submission")->json('run.submission_opened_at');

        $this->travel(2)->days();

        // Someone reopening a submission they never posted is still working on
        // the original one; moving the date forward would read as two attempts.
        $this->postJson("/runs/{$id}/submission")
            ->assertOk()
            ->assertJsonPath('run.submission_opened_at', $first);
    }

    public function test_the_marker_never_reaches_the_public_document(): void
    {
        $id = $this->seedRun('20260807-120000-aaaa');

        $this->postJson("/runs/{$id}/submission")->assertOk();

        $this->getJson("/runs/{$id}/submission")
            ->assertOk()
            ->assertJsonMissingPath('document.submission_opened_at');
    }

    public function test_marking_an_unknown_run_is_a_404(): void
    {
        $this->postJson('/runs/20990101-000000-zzzz/submission')->assertNotFound();
    }
}
