<?php

namespace Tests\Feature;

use App\Actions\Results\HttpBenchmarkResults;
use App\Actions\Specs\ServingRuntime;
use App\Support\BenchmarkStages;
use App\Support\Http\LoadProfile;
use App\Support\HttpBenchCommand;
use Tests\TestCase;

/**
 * Facts the server owns that a JavaScript file states again.
 *
 * Each of these had a comment asking whoever changed one side to remember the
 * other. A comment cannot fail, so these assert it instead — following the
 * pattern SchemaVersionTest already set for the community validator.
 *
 * Where a value could simply be shared it was; what is left is genuinely
 * cross-language, either because it runs on a bare Node runner against
 * untrusted forks or because the JavaScript copy is a preview rather than a
 * driver of the same behaviour.
 */
class CrossLanguageDriftTest extends TestCase
{
    protected function source(string $path): string
    {
        $full = base_path($path);

        $this->assertFileExists($full, $path.' is gone, and something still depends on what it says.');

        return (string) file_get_contents($full);
    }

    /**
     * @return array<int, string>
     */
    protected function jsArrayOfStrings(string $source, string $name): array
    {
        preg_match('/'.preg_quote($name, '/').'\s*=\s*\[(.*?)\]/s', $source, $matches);

        $this->assertNotEmpty($matches, "Could not find {$name}.");
        preg_match_all("/'([^']+)'/", $matches[1], $values);

        return $values[1];
    }

    public function test_the_start_screen_previews_the_stages_the_server_would_actually_run(): void
    {
        $source = $this->source('resources/js/Composables/useBenchmarkQueue.js');
        $stages = new BenchmarkStages;

        preg_match('/const enabledBy = \{(.*?)\n\};/s', $source, $matches);
        $this->assertNotEmpty($matches, 'Could not find enabledBy in useBenchmarkQueue.js.');

        preg_match_all('/(\w+): \(\) => form\.(\w+)/', $matches[1], $pairs, PREG_SET_ORDER);
        $this->assertNotEmpty($pairs);

        foreach ($pairs as [, $stage, $setting]) {
            $this->assertSame(
                [$stage],
                array_values($stages->enabled([$setting => true])),
                "useBenchmarkQueue.js says {$setting} enables {$stage}; BenchmarkStages disagrees."
            );
        }
    }

    public function test_the_stage_list_is_in_the_order_the_server_runs_them(): void
    {
        $keys = $this->jsArrayOfStrings($this->source('resources/js/stages.js'), 'STAGES');

        $this->assertSame(
            BenchmarkStages::ORDER,
            array_values(array_intersect($keys, BenchmarkStages::ORDER)),
            'stages.js and BenchmarkStages::ORDER list the stages in different orders.'
        );
    }

    public function test_the_run_estimate_uses_the_durations_the_run_is_configured_with(): void
    {
        $source = $this->source('resources/js/Composables/useSettings.js');
        $sweep = config('benchmark.http.sweep');

        $expected = [
            'HTTP_WARMUP_SECONDS' => $sweep['warmup_seconds'],
            'HTTP_LEVEL_SECONDS' => $sweep['level_seconds'],
            'HTTP_LATENCY_SECONDS' => $sweep['latency_seconds'],
            'HTTP_MAX_LEVELS' => LoadProfile::MAX_LEVELS,
            'HTTP_ROUTES' => count(HttpBenchmarkResults::ROUTES),
            'HTTP_SETTLE_SECONDS' => HttpBenchCommand::SETTLE_SECONDS,
        ];

        foreach ($expected as $name => $value) {
            preg_match('/const '.$name.' = (\d+)/', $source, $matches);

            $this->assertNotEmpty($matches, "Could not find {$name} in useSettings.js.");
            $this->assertSame(
                $value,
                (int) $matches[1],
                "useSettings.js has {$name} = {$matches[1]}, so the start screen would misstate how long a run takes."
            );
        }
    }

    public function test_the_community_validator_knows_every_server_this_app_can_report(): void
    {
        $this->assertSame(
            ServingRuntime::SERVERS,
            $this->jsArrayOfStrings($this->source('docs/shared/submission/validate.mjs'), 'KNOWN_SERVERS'),
            'A server the app can report but the validator does not know is a run the gallery turns away.'
        );
    }
}
