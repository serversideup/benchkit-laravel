<?php

namespace Tests\Unit;

use App\Support\HttpSummaryReport;
use Tests\TestCase;

class HttpSummaryReportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function detail(array $overrides = []): array
    {
        return array_merge([
            'path' => '/bench/static',
            'requests_per_second' => 5623.4,
            'total_requests' => 56238,
            'duration_seconds' => 10.0,
            'success_rate' => 1.0,
            'bytes_per_second' => 12960890.0,
            'total_bytes' => 1804000,
            'average_ms' => 8.9,
            'fastest_ms' => 1.2,
            'slowest_ms' => 23.4,
            'latency_ms' => ['p50' => 8.5, 'p90' => 12.1, 'p95' => 14.0, 'p99' => 18.0],
            'status_codes' => ['200' => 56238],
            'errors' => [],
        ], $overrides);
    }

    public function test_it_renders_headline_metrics_with_thousands_separators(): void
    {
        $text = implode("\n", (new HttpSummaryReport)->lines($this->detail()));

        $this->assertStringContainsString('Requests/sec   5,623.4', $text);
        $this->assertStringContainsString('56,238 completed in 10.00s', $text);
        $this->assertStringContainsString('success 100.0%', $text);
        $this->assertStringContainsString('p95 14.00', $text);
        $this->assertStringContainsString('Status         200: 56,238', $text);
    }

    public function test_it_formats_throughput_in_human_units(): void
    {
        $text = implode("\n", (new HttpSummaryReport)->lines($this->detail()));

        $this->assertStringContainsString('Throughput     12.36 MB/s', $text);
        $this->assertStringContainsString('(1.72 MB transferred)', $text);
    }

    public function test_it_shows_errors_only_when_present(): void
    {
        $clean = implode("\n", (new HttpSummaryReport)->lines($this->detail()));
        $this->assertStringNotContainsString('Errors', $clean);

        $withErrors = implode("\n", (new HttpSummaryReport)->lines($this->detail([
            'errors' => ['aborted due to deadline' => 50],
        ])));
        $this->assertStringContainsString('Errors         aborted due to deadline: 50', $withErrors);
    }

    public function test_it_degrades_gracefully_when_values_are_missing(): void
    {
        $text = implode("\n", (new HttpSummaryReport)->lines($this->detail([
            'duration_seconds' => null,
            'success_rate' => null,
            'bytes_per_second' => null,
            'average_ms' => null,
            'latency_ms' => ['p50' => null, 'p90' => null, 'p95' => null, 'p99' => null],
        ])));

        $this->assertStringContainsString('completed in —s', $text);
        $this->assertStringContainsString('success —', $text);
        $this->assertStringNotContainsString('Throughput', $text);
        $this->assertStringContainsString('avg — · p50 —', $text);
    }
}
