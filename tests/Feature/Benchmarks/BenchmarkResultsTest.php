<?php

namespace Tests\Feature\Benchmarks;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BenchmarkResultsTest extends TestCase
{
    protected string $resultsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resultsPath = sys_get_temp_dir().'/benchkit-results-'.uniqid();
        File::ensureDirectoryExists($this->resultsPath);
        config(['benchmark.results_path' => $this->resultsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->resultsPath);

        parent::tearDown();
    }

    public function test_yabs_results_returns_no_results_when_no_run_has_happened(): void
    {
        $this->getJson('/yabs/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_yabs_results_returns_the_run_output(): void
    {
        File::put($this->resultsPath.'/yabs-results.json', json_encode([
            'geekbench' => [['single' => 1000, 'multi' => 2000, 'url' => 'https://browser.geekbench.com/v6/cpu/1']],
        ]));

        $this->getJson('/yabs/results')
            ->assertOk()
            ->assertJsonPath('geekbench.0.single', 1000);
    }

    public function test_php_results_returns_no_results_when_no_run_has_happened(): void
    {
        $this->getJson('/php/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_php_results_maps_headline_crud_subjects(): void
    {
        File::put($this->resultsPath.'/phpbench_results.csv', implode("\n", [
            'benchmark,subject,revs,its,mem_peak,best,mean,mode,worst,stdev,rstdev',
            'InsertBenchmark,benchDbFacadeInsertIndividual,10,5,100,90000,95500,95000,99000,100,1.0',
            'QueryBenchmark,benchSelectWithLimit,1000,5,100,900,1250,1200,1400,10,1.0',
            'QueryBenchmark,benchSimpleSelect,1000,5,100,9000,12500,12000,14000,10,1.0',
            'UpdateBenchmark,benchQueryBuilderIndividual,10,5,100,80000,84200,84000,89000,100,1.0',
            'DeleteBenchmark,benchQueryBuilderIndividual,5,5,100,220000,233400,232000,239000,100,1.0',
        ]));

        $response = $this->getJson('/php/results')->assertOk();

        $response->assertJsonPath('phpbench_results.create.milliseconds', 95.5);
        $response->assertJsonPath('phpbench_results.read.milliseconds', 1.3);
        $response->assertJsonPath('phpbench_results.update.milliseconds', 84.2);
        $response->assertJsonPath('phpbench_results.delete.milliseconds', 233.4);
        $response->assertJsonPath('phpbench_results.create.records', 100);
    }

    public function test_php_results_returns_null_metrics_when_a_subject_is_missing(): void
    {
        File::put($this->resultsPath.'/phpbench_results.csv', implode("\n", [
            'benchmark,subject,revs,its,mem_peak,best,mean,mode,worst,stdev,rstdev',
            'InsertBenchmark,benchDbFacadeInsertIndividual,10,5,100,90000,95500,95000,99000,100,1.0',
        ]));

        $response = $this->getJson('/php/results')->assertOk();

        $response->assertJsonPath('phpbench_results.create.milliseconds', 95.5);
        $this->assertNull($response->json('phpbench_results.read.milliseconds'));
    }

    public function test_php_results_handles_an_empty_csv_from_an_aborted_run(): void
    {
        File::put($this->resultsPath.'/phpbench_results.csv', '');

        $this->getJson('/php/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_cfspeedtest_results_returns_no_results_when_no_run_has_happened(): void
    {
        $this->getJson('/cfspeedtest/results')
            ->assertNotFound()
            ->assertJson(['status' => 'no_results']);
    }

    public function test_cfspeedtest_results_parses_the_persisted_output(): void
    {
        File::put($this->resultsPath.'/cfspeedtest-output.txt', implode("\n", [
            'Asn: 13335',
            'Colo: ORD',
            'Avg GET request latency 17.11 ms (RTT excluding server processing time)',
            'Download  10MB  |  min 200.04   max 400.87   avg 300.25',
            'Download  25MB  |  min 250.04   max 450.87   avg 350.75',
            'Upload    10MB  |  min 18.04    max 83.87    avg 41.11',
        ]));

        $this->getJson('/cfspeedtest/results')
            ->assertOk()
            ->assertJson([
                'cfspeedtest_results' => [
                    'asn' => '13335',
                    'colo' => 'ORD',
                    'latency_ms' => 17.11,
                    'download_mbps' => 350.75,
                    'upload_mbps' => 41.11,
                ],
            ]);
    }

    public function test_cfspeedtest_results_parses_the_v2_output_format_without_an_asn_line(): void
    {
        File::put($this->resultsPath.'/cfspeedtest-output.txt', implode("\n", [
            'Starting Cloudflare speed test',
            'Country: US',
            'Ip: 1.2.3.4',
            'Colo: LAX',
            'Avg GET request latency 18.00 ms',
            'Download  25MB   |  min 220.42  max 323.23  avg 292.53',
            'Upload    10MB   |  min 33.66   max 37.54   avg 35.84',
        ]));

        $response = $this->getJson('/cfspeedtest/results')->assertOk();

        $response->assertJson([
            'cfspeedtest_results' => [
                'colo' => 'LAX',
                'latency_ms' => 18.0,
                'download_mbps' => 292.53,
                'upload_mbps' => 35.84,
            ],
        ]);
        $this->assertNull($response->json('cfspeedtest_results.asn'));
    }
}
