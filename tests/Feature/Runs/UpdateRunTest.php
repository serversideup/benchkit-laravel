<?php

namespace Tests\Feature\Runs;

use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRunSnapshots;
use Tests\TestCase;

class UpdateRunTest extends TestCase
{
    use SeedsRunSnapshots;

    protected function seedRunWithHostMeta(string $id = '20260708-165231-k3f9'): string
    {
        return $this->seedRun($id, [
            'meta' => [
                'label' => 'fpm-nginx · full · 2026-07-08 16:52',
                'provider' => 'DIGITALOCEAN-ASN',
                'provider_source' => 'ripe',
                'plan' => null,
                'datacenter' => null,
                'cost' => null,
            ],
        ]);
    }

    public function test_label_and_hosting_details_can_be_updated(): void
    {
        $id = $this->seedRunWithHostMeta();

        $this->patchJson("/runs/{$id}", [
            'label' => 'FrankenPHP test',
            'provider' => 'Vultr',
            'plan' => 'High Frequency 1GB',
            'datacenter' => 'EWR',
            'cost' => '$6/mo',
        ])->assertOk()
            ->assertJsonPath('run.meta.label', 'FrankenPHP test')
            ->assertJsonPath('run.meta.provider', 'Vultr')
            ->assertJsonPath('run.meta.provider_source', 'user')
            ->assertJsonPath('run.meta.plan', 'High Frequency 1GB')
            ->assertJsonPath('run.meta.datacenter', 'EWR')
            ->assertJsonPath('run.meta.cost', '$6/mo');

        $stored = json_decode(Storage::disk('runs')->get("{$id}.json"), true);
        $this->assertSame('FrankenPHP test', $stored['meta']['label']);
        $this->assertSame('user', $stored['meta']['provider_source']);
        $this->assertSame('EWR', $stored['meta']['datacenter']);
    }

    public function test_partial_update_leaves_other_meta_untouched(): void
    {
        $id = $this->seedRunWithHostMeta();

        $this->patchJson("/runs/{$id}", ['label' => 'Renamed'])->assertOk();

        $stored = json_decode(Storage::disk('runs')->get("{$id}.json"), true);
        $this->assertSame('Renamed', $stored['meta']['label']);
        $this->assertSame('DIGITALOCEAN-ASN', $stored['meta']['provider']);
        $this->assertSame('ripe', $stored['meta']['provider_source']);
    }

    public function test_updating_an_unknown_run_returns_404(): void
    {
        $this->patchJson('/runs/20990101-000000-zzzz', ['label' => 'Nope'])->assertNotFound();
    }

    public function test_overlong_hosting_details_are_rejected(): void
    {
        $id = $this->seedRunWithHostMeta();

        $this->patchJson("/runs/{$id}", ['plan' => str_repeat('a', 121)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }
}
