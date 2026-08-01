<?php

namespace Tests\Feature\Benchmarks;

use App\Support\BenchmarkStages;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

/**
 * The settings a run is started with decide which stages run and what each
 * one is told to do. This is the server-side half of a map the frontend
 * also holds (resources/js/Composables/useBenchmarkQueue.js), so these
 * tests pin the settings keys both sides have to agree on.
 */
class BenchmarkStagesTest extends TestCase
{
    use UsesFakeResultsPath;

    protected BenchmarkStages $stages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stages = new BenchmarkStages;
    }

    public function test_every_stage_runs_when_everything_is_enabled(): void
    {
        $enabled = $this->stages->enabled([
            'hardware' => true,
            'network' => true,
            'http' => true,
            'php_database' => true,
        ]);

        $this->assertSame(['yabs', 'cfspeedtest', 'http', 'php'], $enabled);
    }

    public function test_stages_are_selected_by_their_own_settings_key(): void
    {
        $this->assertSame(['cfspeedtest'], $this->stages->enabled([
            'hardware' => false,
            'network' => true,
            'http' => false,
            'php_database' => false,
        ]));
    }

    public function test_settings_that_enable_nothing_produce_no_stages(): void
    {
        $this->assertSame([], $this->stages->enabled([]));
    }

    /**
     * The mode-to-command mapping itself is covered by PhpBenchCommandTest;
     * this checks the run's settings reach it.
     */
    #[DataProvider('phpModes')]
    public function test_the_php_stage_carries_the_requested_mode(?string $mode, string $expected): void
    {
        $settings = $mode === null ? [] : ['php_mode' => $mode];

        $this->assertStringContainsString($expected, $this->stages->resolve('php', $settings)['command']);
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function phpModes(): array
    {
        return [
            'quick limits the suite' => ['quick', '--iterations=2'],
            'full runs everything' => ['full', '--report=comparison'],
            'omitted defaults to full' => [null, '--report=comparison'],
        ];
    }

    public function test_disabled_hardware_tests_are_opted_out_of(): void
    {
        $command = $this->stages->resolve('yabs', [
            'disk' => false,
            'geekbench' => false,
            'iperf' => false,
        ])['command'];

        $this->assertStringContainsString(' -f', $command);
        $this->assertStringContainsString(' -g', $command);
        $this->assertStringContainsString(' -i', $command);
    }

    public function test_the_requested_geekbench_version_is_used(): void
    {
        $command = $this->stages->resolve('yabs', [
            'geekbench' => true,
            'geekbench_version' => 5,
        ])['command'];

        $this->assertStringContainsString(' -5', $command);
    }

    /**
     * cfspeedtest publishes no machine-readable results, so its console
     * output is collected to a file and parsed back out afterwards.
     */
    public function test_the_network_stage_collects_its_console_output(): void
    {
        $stage = $this->stages->resolve('cfspeedtest', ['network_test_type' => 'ipv6']);

        $this->assertStringContainsString('--ipv6', $stage['command']);
        $this->assertSame($this->resultsPath.'/cfspeedtest-output.txt', $stage['collect']);
    }
}
