<?php

namespace Tests\Feature;

use App\Actions\Results\AssembleResultsDocument;
use App\Actions\Specs\WebRuntimeSpecs;
use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

class UnifiedResultsTest extends TestCase
{
    use UsesFakeResultsPath;

    public function test_unified_results_document_includes_environment_and_benchmark_sections(): void
    {
        File::put($this->resultsPath.'/yabs-results.json', json_encode([
            'geekbench' => [['single' => 1000, 'multi' => 2000]],
        ]));

        $response = $this->getJson('/results')->assertOk();

        $response->assertJsonPath('schema_version', AssembleResultsDocument::SCHEMA_VERSION);
        $response->assertJsonStructure([
            'schema_version',
            'generated_at',
            'environment' => [
                'server',
                'php' => ['php_version', 'php_server_api', 'php_variation', 'octane', 'memory_limit', 'op_cache', 'op_cache_jit', 'op_cache_jit_buffer_size'],
                'php_environment_source',
                'laravel',
                'database' => ['driver', 'version', 'filesystem', 'durability'],
                'php_variation',
                'build_version',
            ],
            'benchmarks' => ['yabs', 'cfspeedtest', 'http', 'php'],
        ]);

        $response->assertJsonPath('benchmarks.yabs.geekbench.0.single', 1000);
        $this->assertNull($response->json('benchmarks.cfspeedtest'));
        $this->assertNull($response->json('benchmarks.php'));
    }

    /**
     * Without the HTTP stage there is nothing to ask, so the environment is the
     * one belonging to the process assembling the document. That is a different
     * SAPI with a different opcache and memory limit, and the document has to
     * say so rather than let a reader assume it describes their web server.
     */
    public function test_the_environment_says_it_came_from_the_cli_when_no_web_runtime_was_captured(): void
    {
        $response = $this->getJson('/results')->assertOk();

        $response->assertJsonPath('environment.php_environment_source', 'cli');
        $response->assertJsonPath('environment.php.php_server_api', PHP_SAPI);
    }

    /**
     * When the HTTP stage did reach the application, the run reports what PHP
     * looks like over there instead — the SAPI, opcache, and pool that produced
     * the throughput numbers sitting next to it.
     */
    public function test_the_environment_prefers_the_runtime_captured_from_the_web_server(): void
    {
        File::put($this->resultsPath.'/'.WebRuntimeSpecs::FILE, json_encode([
            'php_version' => '8.5.9',
            'php_server_api' => 'fpm-fcgi',
            'op_cache' => '1',
            'memory_limit' => '512M',
            'ini' => ['opcache.enable' => '1'],
        ]));

        $response = $this->getJson('/results')->assertOk();

        $response->assertJsonPath('environment.php_environment_source', 'web');
        $response->assertJsonPath('environment.php.php_server_api', 'fpm-fcgi');
        $response->assertJsonPath('environment.php.memory_limit', '512M');
    }

    /**
     * A file that is not recognisably a runtime is ignored rather than merged,
     * so a truncated write or an older build answering /bench/env with
     * something else cannot replace the environment with a fragment of one.
     */
    public function test_an_unrecognisable_captured_runtime_falls_back_to_the_cli(): void
    {
        File::put($this->resultsPath.'/'.WebRuntimeSpecs::FILE, json_encode(['not' => 'a runtime']));

        $this->getJson('/results')->assertOk()->assertJsonPath('environment.php_environment_source', 'cli');
    }

    public function test_unified_results_document_includes_all_phpbench_subjects(): void
    {
        File::put($this->resultsPath.'/phpbench_results.csv', implode("\n", [
            'benchmark,subject,revs,its,mem_peak,best,mean,mode,worst,stdev,rstdev',
            'InsertBenchmark,benchDbFacadeInsertIndividual,10,5,100,90000,95500,95000,99000,100,1.0',
            'StringBenchmark,benchStrSlug,1000,5,100,10,12,11,15,1,1.0',
        ]));

        $response = $this->getJson('/results')->assertOk();

        $response->assertJsonPath('benchmarks.php.headline.create.milliseconds', 95.5);
        $response->assertJsonCount(2, 'benchmarks.php.subjects');
        $response->assertJsonPath('benchmarks.php.subjects.1.subject', 'benchStrSlug');
    }
}
