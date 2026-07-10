<?php

namespace Tests\Feature\Runs;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteRunTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('runs');
    }

    public function test_a_run_can_be_deleted(): void
    {
        $id = '20260708-165231-k3f9';
        Storage::disk('runs')->put("{$id}.json", json_encode(['type' => 'benchkit-run', 'id' => $id]));

        $this->deleteJson("/runs/{$id}")->assertNoContent();

        Storage::disk('runs')->assertMissing("{$id}.json");
    }

    public function test_deleting_an_unknown_run_returns_404(): void
    {
        $this->deleteJson('/runs/20990101-000000-zzzz')->assertNotFound();
    }

    public function test_traversal_shaped_ids_are_rejected_by_the_route(): void
    {
        $this->deleteJson('/runs/../../.env')->assertNotFound();
    }
}
