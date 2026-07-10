<?php

namespace App\Actions\Runs;

use Illuminate\Support\Facades\Storage;

class DeleteRun
{
    public function execute(string $id): bool
    {
        if ((new FindRun)->execute($id) === null) {
            return false;
        }

        return Storage::disk('runs')->delete("{$id}.json");
    }
}
