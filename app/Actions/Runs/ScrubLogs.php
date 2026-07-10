<?php

namespace App\Actions\Runs;

class ScrubLogs
{
    /**
     * The raw cfspeedtest console output leaks the server's public IP
     * (an "Ip: x.x.x.x" line) — the parsed results deliberately omit it,
     * and stored logs must too.
     *
     * @param  array<string, array<int, string>>  $logs
     * @return array<string, array<int, string>>
     */
    public function execute(array $logs): array
    {
        if (isset($logs['cfspeedtest'])) {
            $logs['cfspeedtest'] = array_values(array_filter(
                $logs['cfspeedtest'],
                fn (string $line): bool => ! $this->exposesIpAddress($line),
            ));
        }

        return $logs;
    }

    protected function exposesIpAddress(string $line): bool
    {
        if (preg_match('/^\s*Ip:/i', $line)) {
            return true;
        }

        if (preg_match('/\b\d{1,3}(\.\d{1,3}){3}\b/', $line)) {
            return true;
        }

        return (bool) preg_match('/\b[0-9a-f]{1,4}(:[0-9a-f]{1,4}){3,}\b/i', $line);
    }
}
