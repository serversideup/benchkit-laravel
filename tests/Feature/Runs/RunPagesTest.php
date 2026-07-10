<?php

namespace Tests\Feature\Runs;

use Inertia\Testing\AssertableInertia;
use Tests\Concerns\SeedsRunSnapshots;
use Tests\TestCase;

class RunPagesTest extends TestCase
{
    use SeedsRunSnapshots;

    public function test_runs_index_lists_runs_newest_first_without_heavy_payloads(): void
    {
        $this->seedRun('20260707-120000-aaaa');
        $this->seedRun('20260708-120000-bbbb');

        $this->get('/runs')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Runs/Index')
            ->count('runs', 2)
            ->where('runs.0.id', '20260708-120000-bbbb')
            ->where('runs.1.id', '20260707-120000-aaaa')
            ->missing('runs.0.logs')
            ->missing('runs.0.benchmarks')
        );
    }

    public function test_run_show_page_provides_the_full_snapshot(): void
    {
        $id = $this->seedRun('20260708-165231-k3f9');

        $this->get("/runs/{$id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Runs/Show')
            ->where('run.id', $id)
            ->where('run.summary.http_rps', 34.1)
            ->has('run.logs.http')
        );
    }

    public function test_run_show_returns_404_for_unknown_run(): void
    {
        $this->get('/runs/20990101-000000-zzzz')->assertNotFound();
    }

    public function test_compare_page_provides_both_snapshots_and_the_run_index(): void
    {
        $a = $this->seedRun('20260707-120000-aaaa');
        $b = $this->seedRun('20260708-120000-bbbb');

        $this->get("/compare/{$a}/{$b}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Runs/Compare')
            ->where('runA.id', $a)
            ->where('runB.id', $b)
            ->count('runs', 2)
        );
    }

    public function test_compare_returns_404_when_either_run_is_missing(): void
    {
        $a = $this->seedRun('20260707-120000-aaaa');

        $this->get("/compare/{$a}/20990101-000000-zzzz")->assertNotFound();
    }

    public function test_home_page_includes_recent_runs(): void
    {
        $this->seedRun('20260705-120000-aaaa');
        $this->seedRun('20260706-120000-bbbb');
        $this->seedRun('20260707-120000-cccc');
        $this->seedRun('20260708-120000-dddd');

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Index')
            ->count('recentRuns', 3)
            ->where('recentRuns.0.id', '20260708-120000-dddd')
        );
    }
}
