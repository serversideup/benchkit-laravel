<?php

namespace App\Actions\Runs;

use Illuminate\Support\Facades\Storage;

/**
 * Records that a submission was opened for a run.
 *
 * A run id is minted once per benchmark, so submitting the same run twice is a
 * resubmission the bot rejects — and until now the app had no way of knowing
 * that, so it offered the button identically for a fresh run and one already in
 * the gallery. The submitter only found out after a round trip to GitHub and a
 * closed issue.
 *
 * Stored on the snapshot rather than in the browser because a run is owned by
 * the server: the same run opened in another tab, browser, or device should
 * carry the same warning.
 *
 * Deliberately named for what actually happened. Clicking submit opens a
 * pre-filled issue; whether the submitter posted it, and whether a maintainer
 * merged it, is not something this instance can see. So the UI warns rather
 * than blocks.
 */
class MarkRunSubmitted
{
    /**
     * @return array<string, mixed>|null  the updated snapshot, or null if unknown
     */
    public function execute(string $id): ?array
    {
        $snapshot = (new FindRun)->execute($id);

        if ($snapshot === null) {
            return null;
        }

        // First attempt only: someone re-opening a submission they never posted
        // is still working on the original one, and moving the date forward
        // would make the warning read as though they'd submitted twice.
        $snapshot['submission_opened_at'] ??= now()->utc()->toIso8601String();

        Storage::disk('runs')->put("{$id}.json", json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $snapshot;
    }
}
