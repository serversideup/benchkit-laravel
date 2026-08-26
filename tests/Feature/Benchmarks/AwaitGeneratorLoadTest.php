<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Support\GeneratorSession;
use App\Support\Http\LoadProfile;
use App\Support\Http\LoadSizing;
use App\Support\Http\LoadStep;
use App\Support\RunState;
use Illuminate\Support\Facades\File;
use Tests\Concerns\SeedsHttpResults;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\Concerns\UsesFakeRunPath;
use Tests\TestCase;

class AwaitGeneratorLoadTest extends TestCase
{
    /** Two levels is enough to exercise the wait without a slow fixture. */
    protected const LEVELS = [1, 16];

    use SeedsHttpResults;
    use UsesFakeResultsPath;
    use UsesFakeRunPath;

    /**
     * An armed pairing, as the external HTTP stage leaves it just before the
     * waiting command becomes the stage.
     *
     * @return array<string, mixed>
     */
    protected function armed(): array
    {
        $session = (new GeneratorSession)->create('https://bench.example.com', 'https://bench.example.com');
        $run = (new RunState)->start(['http' => true], ['http'], null);
        (new RunState)->claim(getmypid());

        (new HttpBenchmarkResults)->writeMeta(
            ['url' => 'https://bench.example.com', 'mode' => 'external'],
            new LoadProfile('https://bench.example.com', 'external', 100, 4, 16, self::LEVELS),
            16,
            ['mode' => 'external', 'rtt_ms' => null, 'source_ip' => null, 'oha_version' => null, 'host' => null],
        );

        return (new GeneratorSession)->arm($run['id'], '# work');
    }

    /**
     * One connection per route: the batch every run starts with, and the only
     * one whose levels are known before anything has been measured.
     */
    protected function receiveProbe(float $requestsPerSecond = 1234.56): void
    {
        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $key) {
            $this->receiveSlot(
                HttpBenchmarkResults::slotFor($key, LoadStep::PHASE_SWEEP, LoadProfile::PROBE_CONCURRENCY),
                $requestsPerSecond,
                LoadProfile::PROBE_CONCURRENCY,
            );
        }
    }

    /**
     * The sweep, at whatever levels the probe earned.
     *
     * The test asks the production sizing what those are rather than
     * repeating the formula — the arithmetic has its own unit tests, and what
     * this file is checking is that the three batches hand off to each other.
     */
    protected function receiveSweep(float $requestsPerSecond = 1234.56): void
    {
        $results = new HttpBenchmarkResults;
        $sizing = (new LoadSizing($results))->fromProbe(LoadProfile::fromMeta($results->readMeta()), null);

        foreach ($sizing['levels'] as $key => $levels) {
            foreach ($levels as $connections) {
                if ($connections === LoadProfile::PROBE_CONCURRENCY) {
                    continue;
                }

                $this->receiveSlot(
                    HttpBenchmarkResults::slotFor($key, LoadStep::PHASE_SWEEP, $connections),
                    $requestsPerSecond,
                    $connections,
                );
            }
        }
    }

    /**
     * The response-time windows, which the server arms as a second batch once
     * the sweep has landed — its rate is a fraction of what the sweep proved
     * the server can hold, so it cannot exist before then.
     */
    protected function receiveLatency(float $requestsPerSecond = 800.0): void
    {
        foreach (array_keys(HttpBenchmarkResults::ROUTES) as $key) {
            $this->receiveSlot(
                HttpBenchmarkResults::slotFor($key, LoadStep::PHASE_LATENCY, 0),
                $requestsPerSecond,
                4,
            );
        }
    }

    protected function receiveSlot(string $slot, float $requestsPerSecond, int $connections): void
    {
        $this->writeOha((new HttpBenchmarkResults)->pathForSlot($slot), $requestsPerSecond, $connections);

        (new GeneratorSession)->recordReceived($slot, round($requestsPerSecond, 1), '203.0.113.7');
    }

    public function test_it_completes_once_every_route_has_arrived(): void
    {
        $this->armed();

        $this->receiveProbe();
        $this->receiveSweep();
        $this->receiveLatency();

        $this->artisan('benchmark:await-generator', ['--timeout' => 5])
            ->assertExitCode(0)
            ->expectsOutputToContain('static-c1')
            ->expectsOutputToContain('203.0.113.7')
            // The full block is printed for the response-time windows, which
            // are the figures the results page publishes.
            ->expectsOutputToContain('Requests/sec')
            ->expectsOutputToContain('External load test complete.');

        $this->assertSame(GeneratorSession::STATUS_DONE, (new GeneratorSession)->current()['status']);
    }

    /**
     * A partial delivery is kept: the stage exits successfully so the
     * snapshot records the routes that did arrive, and says so out loud.
     */
    public function test_a_partial_delivery_is_kept_when_the_generator_stops(): void
    {
        $this->armed();
        $this->receiveSlot(HttpBenchmarkResults::slotFor('static', LoadStep::PHASE_SWEEP, 1), 1234.56, 1);
        $this->receiveSlot(HttpBenchmarkResults::slotFor('json', LoadStep::PHASE_SWEEP, 1), 1234.56, 1);

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('Keeping the partial results.');
    }

    public function test_it_fails_when_nothing_ever_arrives(): void
    {
        $this->armed();

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('No generator delivered results in time.');

        $this->assertSame(GeneratorSession::STATUS_ERROR, (new GeneratorSession)->current()['status']);
    }

    public function test_it_reprints_the_pairing_command_while_nothing_is_connected(): void
    {
        $session = $this->armed();

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->expectsOutputToContain('curl -kfsSL https://bench.example.com/bench/generator/'.$session['token'].'/script | sh');
    }

    /**
     * A generator that pairs after the stage armed still gets its details
     * into the meta file — the stage wrote it before the handshake existed.
     */
    public function test_a_late_handshake_is_merged_into_the_meta(): void
    {
        $this->armed();
        (new GeneratorSession)->recordHandshake([
            'oha_version' => '1.4.5',
            'cores' => 8,
            'host' => 'generator-box',
            'rtt_ms' => 1.83,
            'source_ip' => '203.0.113.7',
        ]);
        $this->receiveSlot(HttpBenchmarkResults::slotFor('static', LoadStep::PHASE_SWEEP, 1), 1234.56, 1);

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('Generator connected from 203.0.113.7: generator-box · oha 1.4.5 · 8 cores · RTT 1.83ms.');

        $meta = json_decode(File::get((new HttpBenchmarkResults)->metaPath()), true);
        $this->assertSame(1.83, $meta['generator']['rtt_ms']);
        $this->assertSame('generator-box', $meta['generator']['host']);
        $this->assertSame('external', $meta['generator']['mode']);
    }

    public function test_rejected_uploads_are_explained_in_the_console(): void
    {
        $this->armed();
        (new GeneratorSession)->recordRejection('static', 'the run transferred zero bytes', '203.0.113.7');
        $this->receiveSlot(HttpBenchmarkResults::slotFor('json', LoadStep::PHASE_SWEEP, 1), 1234.56, 1);

        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->expectsOutputToContain('Rejected an upload for static from 203.0.113.7: the run transferred zero bytes');
    }

    public function test_it_fails_when_no_pairing_exists(): void
    {
        $this->artisan('benchmark:await-generator', ['--timeout' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('The generator pairing disappeared');
    }
}
