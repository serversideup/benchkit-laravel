<?php

namespace App\Actions\Results;

class YabsResults extends BenchmarkResults
{
    public function path(): string
    {
        return $this->resultsPath('yabs-results.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function execute(): ?array
    {
        return $this->readJson($this->path());
    }
}
