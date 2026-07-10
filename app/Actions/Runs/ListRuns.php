<?php

namespace App\Actions\Runs;

use Illuminate\Support\Facades\Storage;

class ListRuns
{
    /**
     * Newest-first index of run summaries. Full benchmark payloads and
     * logs are intentionally excluded — this feeds list views.
     *
     * @return array<int, array{id: string, created_at: string, meta: array, stages_completed: array, summary: array}>
     */
    public function execute(): array
    {
        $runs = [];

        foreach (Storage::disk('runs')->files() as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            $snapshot = json_decode(Storage::disk('runs')->get($file), true);

            if (! is_array($snapshot) || ($snapshot['type'] ?? null) !== 'benchkit-run') {
                continue;
            }

            $runs[] = [
                'id' => $snapshot['id'],
                'created_at' => $snapshot['created_at'],
                'meta' => $snapshot['meta'],
                'stages_completed' => $snapshot['stages_completed'],
                'summary' => $snapshot['summary'],
            ];
        }

        usort($runs, fn (array $a, array $b): int => strcmp($b['id'], $a['id']));

        return $runs;
    }
}
