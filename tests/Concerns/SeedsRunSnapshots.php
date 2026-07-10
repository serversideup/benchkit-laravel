<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Fakes the runs disk and seeds minimal, valid run snapshots onto it.
 * Laravel boots the trait automatically via the setUp name convention.
 */
trait SeedsRunSnapshots
{
    protected function setUpSeedsRunSnapshots(): void
    {
        Storage::fake('runs');
    }

    /**
     * Top-level keys in $overrides replace the defaults wholesale.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function seedRun(string $id, array $overrides = []): string
    {
        Storage::disk('runs')->put("{$id}.json", json_encode(array_merge([
            'schema_version' => 1,
            'type' => 'benchkit-run',
            'id' => $id,
            'created_at' => '2026-07-08T16:52:31+00:00',
            'meta' => ['label' => "Run {$id}", 'provider' => null, 'provider_source' => null, 'plan_notes' => null],
            'settings' => ['php_mode' => 'full'],
            'stages_completed' => ['http'],
            'environment' => [],
            'benchmarks' => ['yabs' => null, 'cfspeedtest' => null, 'http' => ['routes' => []], 'php' => null],
            'extras' => ['geekbench_url' => null],
            'summary' => ['http_rps' => 34.1],
            'logs' => ['http' => ['a log line']],
        ], $overrides)));

        return $id;
    }
}
