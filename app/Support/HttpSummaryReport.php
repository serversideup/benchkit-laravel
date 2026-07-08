<?php

namespace App\Support;

/**
 * Renders a completed HTTP load-test route into an aligned, human-readable
 * console block — the kind of detail an expert would want to review — from
 * the normalised array produced by HttpBenchmarkResults::detail().
 */
class HttpSummaryReport
{
    /**
     * @param  array<string, mixed>  $detail
     * @return array<int, string>
     */
    public function lines(array $detail): array
    {
        $lines = [
            sprintf('  Requests/sec   %s', $this->number($detail['requests_per_second'], 1)),
            sprintf(
                '  Requests       %s completed in %ss   ·   success %s',
                $this->number($detail['total_requests']),
                $this->decimal($detail['duration_seconds'], 2),
                $this->percent($detail['success_rate'])
            ),
        ];

        if (($detail['bytes_per_second'] ?? null) !== null) {
            $lines[] = sprintf(
                '  Throughput     %s/s (%s transferred)',
                $this->bytes($detail['bytes_per_second']),
                $this->bytes($detail['total_bytes'])
            );
        }

        $latency = $detail['latency_ms'];

        $lines[] = sprintf(
            '  Latency (ms)   avg %s · p50 %s · p90 %s · p95 %s · p99 %s',
            $this->decimal($detail['average_ms'], 2),
            $this->decimal($latency['p50'], 2),
            $this->decimal($latency['p90'], 2),
            $this->decimal($latency['p95'], 2),
            $this->decimal($latency['p99'], 2)
        );

        $lines[] = sprintf(
            '  Range (ms)     fastest %s · slowest %s',
            $this->decimal($detail['fastest_ms'], 2),
            $this->decimal($detail['slowest_ms'], 2)
        );

        if ($detail['status_codes'] !== []) {
            $lines[] = '  Status         '.$this->distribution($detail['status_codes']);
        }

        if ($detail['errors'] !== []) {
            $lines[] = '  Errors         '.$this->distribution($detail['errors']);
        }

        return $lines;
    }

    protected function number(int|float|null $value, int $decimals = 0): string
    {
        return $value === null ? '—' : number_format($value, $decimals);
    }

    protected function decimal(int|float|null $value, int $decimals): string
    {
        return $value === null ? '—' : number_format($value, $decimals);
    }

    protected function percent(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate * 100, 1).'%';
    }

    protected function bytes(int|float|null $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return $unit === 0
            ? number_format($bytes).' '.$units[$unit]
            : number_format($bytes, 2).' '.$units[$unit];
    }

    /**
     * @param  array<string|int, int>  $distribution
     */
    protected function distribution(array $distribution): string
    {
        $parts = [];

        foreach ($distribution as $label => $count) {
            $parts[] = $label.': '.number_format($count);
        }

        return implode('   ', $parts);
    }
}
