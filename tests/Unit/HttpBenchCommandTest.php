<?php

namespace Tests\Unit;

use App\Support\Http\LoadCurve;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadStep;
use App\Support\Http\StepResult;
use App\Support\HttpBenchCommand;
use Tests\TestCase;

class HttpBenchCommandTest extends TestCase
{
    protected function profile(string $url = 'http://localhost:8080'): LoadProfile
    {
        return new LoadProfile($url, 'loopback', 100, 4, 20, [1, 4, 20]);
    }

    /**
     * @param  array<int, float>  $rpsByConcurrency
     */
    protected function curve(string $route, array $rpsByConcurrency): LoadCurve
    {
        $measurements = [];

        foreach ($rpsByConcurrency as $concurrency => $rps) {
            $average = $concurrency / $rps;

            $measurements[] = ['concurrency' => $concurrency, 'result' => StepResult::fromOha([
                'summary' => ['requestsPerSec' => $rps, 'average' => $average, 'fastest' => $average / 2, 'successRate' => 1.0, 'total' => 6.0],
                'latencyPercentiles' => ['p50' => $average],
                'statusCodeDistribution' => ['200' => (int) ($rps * 6)],
                'errorDistribution' => ['aborted due to deadline' => $concurrency],
            ])];
        }

        return new LoadCurve($route, $measurements);
    }

    /**
     * The probe warms a route, then measures it at one connection — the only
     * point where nothing is queued, so the response time is the server's own
     * work plus a round trip that was already measured at the handshake.
     */
    public function test_the_probe_warms_each_route_then_measures_one_connection(): void
    {
        $steps = (new HttpBenchCommand)->probe($this->profile());
        $static = array_values(array_filter($steps, fn (LoadStep $s): bool => $s->route === 'static'));

        $this->assertSame(LoadStep::PHASE_WARMUP, $static[0]->phase);
        $this->assertSame(LoadStep::PHASE_SWEEP, $static[1]->phase);
        $this->assertSame(1, $static[1]->connections);
        $this->assertCount(2, $static);
    }

    public function test_the_probe_covers_every_route_in_measurement_order(): void
    {
        $routes = array_values(array_unique(array_map(
            fn (LoadStep $s): string => $s->route,
            (new HttpBenchCommand)->probe($this->profile())
        )));

        $this->assertSame(['static', 'json', 'db_read', 'io'], $routes);
    }

    /**
     * The probe has already measured one connection, so the sweep starts above
     * it. Measuring it twice would put two files behind one point on the curve.
     */
    public function test_the_sweep_does_not_repeat_the_level_the_probe_measured(): void
    {
        $steps = (new HttpBenchCommand)->sweep($this->profile(), ['static' => [1, 4, 20]]);

        $this->assertSame([4, 20], array_map(fn (LoadStep $s): int => $s->connections, $steps));
    }

    public function test_each_route_is_swept_at_its_own_levels(): void
    {
        $steps = (new HttpBenchCommand)->sweep($this->profile(), ['static' => [1, 200], 'io' => [1, 40]]);

        $this->assertSame(
            [['static', 200], ['io', 40]],
            array_map(fn (LoadStep $s): array => [$s->route, $s->connections], $steps),
        );
    }

    public function test_the_simulated_delay_rides_only_on_the_io_route(): void
    {
        foreach ((new HttpBenchCommand)->probe($this->profile()) as $step) {
            $step->route === 'io'
                ? $this->assertStringEndsWith('/bench/io?ms=100', $step->url)
                : $this->assertStringNotContainsString('?ms=', $step->url);
        }
    }

    public function test_self_signed_certificates_are_trusted_only_over_tls(): void
    {
        $command = new HttpBenchCommand;
        $step = $command->probe($this->profile())[1];

        $this->assertFalse($command->isInsecure($this->profile()));
        $this->assertStringNotContainsString('--insecure', $command->render($step, false));
        $this->assertStringContainsString('--insecure', $command->render($step, true));
        $this->assertTrue($command->isInsecure($this->profile('https://localhost:8443')));
    }

    public function test_a_sweep_step_is_a_plain_closed_loop_window(): void
    {
        $command = new HttpBenchCommand;
        $rendered = $command->render($command->probe($this->profile())[1], false);

        $this->assertStringContainsString('-c 1', $rendered);
        $this->assertStringContainsString('--output-format json', $rendered);
        $this->assertStringNotContainsString('-q ', $rendered);
        $this->assertStringNotContainsString('--latency-correction', $rendered);
    }

    public function test_the_warmup_is_never_captured(): void
    {
        $command = new HttpBenchCommand;
        $warmup = $command->probe($this->profile())[0];

        $this->assertStringNotContainsString('--output-format json', $command->render($warmup, false));
        $this->assertNull($command->outputPath($warmup));
    }

    public function test_the_response_time_pass_offers_a_rate_below_the_measured_peak(): void
    {
        $command = new HttpBenchCommand;
        $steps = $command->latency($this->profile(), ['static' => $this->curve('static', [1 => 100.0, 4 => 200.0, 20 => 400.0])]);

        $this->assertCount(1, $steps);
        $this->assertSame(LoadStep::PHASE_LATENCY, $steps[0]->phase);
        $this->assertSame(280, $steps[0]->qps);
    }

    public function test_the_response_time_pass_corrects_for_coordinated_omission(): void
    {
        $command = new HttpBenchCommand;
        $step = $command->latency($this->profile(), ['static' => $this->curve('static', [1 => 100.0, 4 => 200.0, 20 => 400.0])])[0];
        $rendered = $command->render($step, false);

        $this->assertStringContainsString('-q 280', $rendered);
        $this->assertStringContainsString('--latency-correction', $rendered);
        // Without -w oha abandons the requests still in flight at the deadline,
        // and those are the slowest ones — dropping them biases the tail down,
        // which is the bias the correction exists to remove.
        $this->assertStringContainsString('-w', $rendered);
    }

    public function test_a_route_that_never_served_correctly_gets_no_response_time_pass(): void
    {
        $broken = new LoadCurve('db_read', [
            ['concurrency' => 1, 'result' => StepResult::fromOha([
                'summary' => ['requestsPerSec' => 900.0, 'average' => 0.001, 'fastest' => 0.001, 'successRate' => 1.0, 'total' => 6.0],
                'latencyPercentiles' => ['p50' => 0.001],
                'statusCodeDistribution' => ['503' => 5400],
            ])],
        ]);

        $this->assertSame([], (new HttpBenchCommand)->latency($this->profile(), ['db_read' => $broken]));
    }

    public function test_each_measured_window_writes_to_its_own_file(): void
    {
        $command = new HttpBenchCommand;
        $paths = [];

        foreach ($command->sweep($this->profile(), ['static' => [1, 4, 20], 'db_read' => [1, 20]]) as $step) {
            $path = $command->outputPath($step);

            if ($path !== null) {
                $paths[] = basename($path);
            }
        }

        $this->assertContains('http-static-c4.json', $paths);
        $this->assertContains('http-db-read-c20.json', $paths);
        $this->assertSame($paths, array_unique($paths), 'Two windows sharing a file would silently overwrite a measurement.');
    }

    /**
     * Every window is a fresh process that resolves the target before it
     * starts, and a run is thirty of them interleaved with load that saturates
     * the machine driving it. A local resolver put under that gave up partway
     * through, and every window after failed with "no records found" — a DNS
     * error in the middle of a test that has nothing to do with DNS.
     *
     * Pinning the address leaves the URL alone, so the Host header and the TLS
     * name presented are still exactly what a real client would send.
     */
    public function test_a_pinned_address_overrides_resolution_without_changing_the_request(): void
    {
        $command = new HttpBenchCommand;
        $profile = new LoadProfile('https://bench.example.com/', 'external', 100, 4, 20, [1, 4], targetIp: '203.0.113.7');

        $rendered = $command->render($command->probe($profile)[1], true, 'oha', $profile->connectTo());

        $this->assertStringContainsString("--connect-to 'bench.example.com:443:203.0.113.7:443'", $rendered);
        $this->assertStringContainsString('https://bench.example.com/bench/static', $rendered);
    }

    public function test_a_trailing_slash_on_the_target_does_not_double_up(): void
    {
        $profile = new LoadProfile('https://bench.example.com/', 'external', 100, 4, 20, [1]);

        $this->assertSame('https://bench.example.com/bench/static', $profile->urlFor('static'));
    }

    public function test_a_target_reached_by_name_over_plain_http_pins_the_right_port(): void
    {
        $profile = new LoadProfile('http://bench.example.com', 'external', 100, 4, 20, [1], targetIp: '203.0.113.7');

        $this->assertSame('bench.example.com:80:203.0.113.7:80', $profile->connectTo());
    }

    public function test_an_explicit_port_is_carried_through(): void
    {
        $profile = new LoadProfile('https://bench.example.com:8443', 'external', 100, 4, 20, [1], targetIp: '203.0.113.7');

        $this->assertSame('bench.example.com:8443:203.0.113.7:8443', $profile->connectTo());
    }

    public function test_nothing_is_pinned_when_the_generator_reported_no_address(): void
    {
        $command = new HttpBenchCommand;
        $profile = $this->profile();

        $this->assertNull($profile->connectTo());
        $this->assertStringNotContainsString('--connect-to', $command->render($command->probe($profile)[1], false));
    }

    public function test_the_generator_runs_whatever_oha_is_on_its_own_path(): void
    {
        $command = new HttpBenchCommand;
        $step = $command->probe($this->profile())[1];

        $this->assertStringStartsWith('oha ', $command->render($step, false, 'oha'));
        $this->assertStringContainsString('vendor/bin/oha', $command->render($step, false));
    }
}
