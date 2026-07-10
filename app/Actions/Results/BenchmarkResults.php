<?php

namespace App\Actions\Results;

abstract class BenchmarkResults
{
    protected function resultsPath(string $file): string
    {
        return config('benchmark.results_path').'/'.$file;
    }

    /**
     * Decoded contents of a JSON results file, or null when the file is
     * missing or unreadable.
     *
     * @return array<mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
