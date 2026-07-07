<?php

namespace App\Actions\Results;

class YabsResults
{
    public function path(): string
    {
        return config('benchmark.results_path').'/yabs-results.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function execute(): ?array
    {
        if (! file_exists($this->path())) {
            return null;
        }

        return json_decode(file_get_contents($this->path()), true);
    }
}
