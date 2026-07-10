<?php

namespace App\Actions\Runs;

use Illuminate\Support\Facades\Storage;

class FindRun
{
    public const ID_PATTERN = '/^\d{8}-\d{6}-[a-z0-9]{4}$/';

    /**
     * The run id becomes a filename on the runs disk, so the format is
     * enforced here as well as in the route constraint — defense in depth
     * against path traversal from any future internal caller.
     *
     * @return array<string, mixed>|null
     */
    public function execute(string $id): ?array
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $contents = Storage::disk('runs')->get("{$id}.json");

        if ($contents === null) {
            return null;
        }

        $snapshot = json_decode($contents, true);

        return is_array($snapshot) ? $snapshot : null;
    }
}
