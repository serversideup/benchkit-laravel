<?php

namespace Tests\Unit;

use App\Support\HttpBenchCommand;
use Tests\TestCase;

class HttpBenchCommandTest extends TestCase
{
    protected function plan(array $target = ['url' => 'http://localhost:8080', 'mode' => 'loopback']): array
    {
        return (new HttpBenchCommand)->plan($target, 30, 50, 100);
    }

    public function test_the_plan_covers_every_route_in_measurement_order(): void
    {
        $this->assertSame(['static', 'json', 'db_read', 'io'], array_column($this->plan(), 'key'));
    }

    public function test_the_plan_carries_the_io_delay_only_on_the_io_route(): void
    {
        foreach ($this->plan() as $route) {
            if ($route['key'] === 'io') {
                $this->assertStringEndsWith('/bench/io?ms=100', $route['url']);
            } else {
                $this->assertStringNotContainsString('?ms=', $route['url']);
            }
        }
    }

    public function test_the_plan_trusts_self_signed_certificates_only_over_tls(): void
    {
        foreach ($this->plan() as $route) {
            $this->assertStringNotContainsString('--insecure', $route['load_flags']);
        }

        foreach ($this->plan(['url' => 'https://localhost:8443', 'mode' => 'loopback']) as $route) {
            $this->assertStringContainsString('--insecure', $route['load_flags']);
        }
    }

    public function test_the_local_chain_warms_up_measures_and_summarises_each_route(): void
    {
        $command = (new HttpBenchCommand)->build(['url' => 'http://localhost:8080', 'mode' => 'loopback'], 30, 50, 100);

        // Warmup is discarded, the measured window lands in the route's JSON
        // file, and the summary prints before the next route starts.
        $this->assertSame(4, substr_count($command, '> /dev/null 2>&1'));
        $this->assertSame(4, substr_count($command, '-z 3s'));
        $this->assertSame(4, substr_count($command, '-z 30s'));
        $this->assertSame(4, substr_count($command, 'benchmark:http-summary'));
        $this->assertStringContainsString('http-static.json', $command);
        $this->assertStringContainsString('http-db-read.json', $command);
        $this->assertStringContainsString('--output-format json', $command);
    }
}
