<?php

namespace App\Actions\Results;

class CloudflareSpeedTestResults
{
    public function path(): string
    {
        return config('benchmark.results_path').'/cfspeedtest-output.txt';
    }

    /**
     * @return array{asn: string|null, colo: string|null, latency_ms: float|null, download_mbps: float|null, upload_mbps: float|null}|null
     */
    public function execute(): ?array
    {
        if (! file_exists($this->path())) {
            return null;
        }

        return $this->parseOutput(file($this->path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    /**
     * Parse the persisted cfspeedtest console output into a summary. Later
     * matches win so the largest-payload download/upload averages are used.
     *
     * @param  array<int, string>  $lines
     * @return array{asn: string|null, colo: string|null, latency_ms: float|null, download_mbps: float|null, upload_mbps: float|null}
     */
    protected function parseOutput(array $lines): array
    {
        $results = [
            'asn' => null,
            'colo' => null,
            'latency_ms' => null,
            'download_mbps' => null,
            'upload_mbps' => null,
        ];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Asn:')) {
                $results['asn'] = trim(explode(':', $line, 2)[1]);
            }

            if (str_starts_with($line, 'Colo:')) {
                $results['colo'] = trim(explode(':', $line, 2)[1]);
            }

            if (preg_match('/Avg GET request latency\s+([0-9.]+)\s*ms/', $line, $matches)) {
                $results['latency_ms'] = (float) $matches[1];
            }

            if (str_starts_with($line, 'Download') && preg_match('/avg\s+([0-9.]+)/', $line, $matches)) {
                $results['download_mbps'] = (float) $matches[1];
            }

            if (str_starts_with($line, 'Upload') && preg_match('/avg\s+([0-9.]+)/', $line, $matches)) {
                $results['upload_mbps'] = (float) $matches[1];
            }
        }

        return $results;
    }
}
