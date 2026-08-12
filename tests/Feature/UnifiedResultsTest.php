<?php

namespace Tests\Feature;

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

        $response->assertJsonPath('schema_version', 3);
        $response->assertJsonStructure([
            'schema_version',
            'generated_at',
            'environment' => [
                'server',
                'php' => ['php_version', 'php_server_api', 'php_variation', 'octane', 'memory_limit', 'op_cache', 'op_cache_jit', 'op_cache_jit_buffer_size'],
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
