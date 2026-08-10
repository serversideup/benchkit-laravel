<?php

namespace Tests\Feature\Runs;

use App\Actions\Runs\EncodeSubmissionToken;
use App\Support\SubmissionIssue;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRunSnapshots;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use SeedsRunSnapshots;

    /**
     * A snapshot carrying every piece of data that must never reach a public
     * repo, so the allow-list is tested against the thing it exists to stop
     * rather than against a clean fixture.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function seedSensitiveRun(string $id = '20260806-205606-kitm', array $overrides = []): string
    {
        return $this->seedRun($id, array_merge([
            'meta' => [
                'label' => 'fpm-nginx (Quick)',
                'provider' => 'DigitalOcean',
                'plan' => 's-4vcpu-8gb',
                'datacenter' => 'nyc3',
                'cost' => '$48/mo',
            ],
            'settings_preset' => 'quick',
            'settings' => ['geekbench_version' => '6'],
            'stages_completed' => ['yabs', 'cfspeedtest', 'http', 'php'],
            'environment' => [
                'server' => [
                    'cpu_model' => 'DO-Premium-Intel',
                    'cpu_cores' => 4,
                    'cpu_frequency' => '2494.140',
                    'os' => 'Debian GNU/Linux 12',
                    'ram' => '7.8 GiB',
                ],
                'php' => [
                    'php_version' => '8.5.0',
                    'php_variation' => 'fpm-nginx',
                    'php_server_api' => 'fpm-fcgi',
                    'octane' => false,
                    'op_cache' => '1',
                    'memory_limit' => '256M',
                    'ini' => [
                        'opcache.enable' => '1',
                        'opcache.jit' => 'tracing',
                        'opcache.preload' => '/var/www/html/preload.php',
                        'memory_limit' => '256M',
                        'zend.assertions' => false,
                    ],
                    'serving' => ['fpm_pm' => 'dynamic', 'fpm_max_children' => '24'],
                ],
                'laravel' => [
                    'environment' => [
                        'laravel_version' => '13.0.1',
                        'url' => 'https://bench.acme-corp.internal',
                    ],
                    'drivers' => ['database' => 'sqlite'],
                ],
                'build_version' => '1.4.2',
            ],
            'benchmarks' => [
                'http' => [
                    'mode' => 'standard',
                    'target' => 'https://bench.acme-corp.internal',
                    'duration_seconds' => 10,
                    'connections' => 50,
                    'io_ms' => 50,
                    'fpm_max_children' => 24,
                    'pool_limited' => false,
                    'routes' => [
                        'json' => [
                            'path' => '/bench/json',
                            'requests_per_second' => 12481.42,
                            'success_rate' => 1.0,
                            'p50_ms' => 1.8,
                            'p95_ms' => 2.14,
                            'p99_ms' => 4.02,
                            'total_requests' => 124814,
                            'status_codes' => ['200' => 124814, 'not-a-code' => 5],
                        ],
                    ],
                ],
                'php' => [
                    'headline' => ['read' => ['milliseconds' => 12.5, 'records' => 1000, 'label' => 'Read']],
                    'subjects' => [
                        ['benchmark' => 'CrudBenchmark', 'subject' => 'benchRead', 'mean_us' => 120.5],
                        ['benchmark' => 'Bad Name!', 'subject' => 'benchRead', 'mean_us' => 1.0],
                    ],
                ],
                'cfspeedtest' => [
                    'latency_ms' => 3.4,
                    'download_mbps' => 940.2,
                    'upload_mbps' => 810.7,
                    'asn' => 'AS14061',
                    'colo' => 'EWR',
                ],
                'yabs' => [
                    'ip_info' => ['ip' => '203.0.113.42', 'isp' => 'Acme Broadband', 'city' => 'Portland'],
                    'geekbench' => [['single' => 2891, 'multi' => 11204, 'url' => 'https://browser.geekbench.com/v6/cpu/1234567']],
                    'fio' => [['bs' => '4k', 'speed_r' => 12345, 'speed_w' => 12300, 'speed_rw' => 24645]],
                ],
            ],
            'extras' => ['geekbench_url' => 'https://browser.geekbench.com/v6/cpu/1234567'],
            'logs' => ['http' => ['Benchmarking https://bench.acme-corp.internal from 203.0.113.42']],
        ], $overrides));
    }

    public function test_submission_endpoint_returns_a_token_the_gallery_can_decode(): void
    {
        $id = $this->seedSensitiveRun();

        $response = $this->getJson("/runs/{$id}/submission")->assertOk();

        $token = $response->json('token');

        $this->assertMatchesRegularExpression('/^bk1\.[A-Za-z0-9_-]+\.[0-9a-f]{16}$/', $token);
        $this->assertTrue($response->json('prefill'));
        $this->assertSame(strlen($token), $response->json('bytes'));
        $this->assertSame($response->json('document'), EncodeSubmissionToken::decode($token));
    }

    public function test_the_token_is_dramatically_smaller_than_the_json_it_carries(): void
    {
        $id = $this->seedSensitiveRun();

        $response = $this->getJson("/runs/{$id}/submission")->assertOk();

        $json = json_encode($response->json('document'), JSON_PRETTY_PRINT);

        // The regression this whole format exists for: GitHub truncated a
        // 7,121-character issue URL mid-number. Both the token and the URL it
        // rides in have to stay far under that.
        $this->assertLessThan(strlen($json) / 2, $response->json('bytes'));
        $this->assertLessThan(SubmissionIssue::MAX_URL_LENGTH, strlen($response->json('issue_url')));
    }

    public function test_a_tampered_token_does_not_decode(): void
    {
        $id = $this->seedSensitiveRun();

        $token = $this->getJson("/runs/{$id}/submission")->json('token');

        [$version, $payload, $digest] = explode('.', $token);

        // Mutated in the middle: a deflate stream self-terminates, so anything
        // appended to the end is ignored rather than corrupting the payload.
        $mutated = substr_replace($payload, $payload[10] === 'A' ? 'B' : 'A', 10, 1);

        $this->assertNull(EncodeSubmissionToken::decode("{$version}.{$payload}.0000000000000000"), 'a rewritten digest should not decode');
        $this->assertNull(EncodeSubmissionToken::decode(substr($token, 0, -20)), 'a truncated token should not decode');
        $this->assertNull(EncodeSubmissionToken::decode("{$version}.{$mutated}.{$digest}"), 'a corrupted payload should not decode');
    }

    public function test_the_document_withholds_everything_that_identifies_the_submitter(): void
    {
        $id = $this->seedSensitiveRun();

        $document = $this->getJson("/runs/{$id}/submission")->json('document');
        $encoded = json_encode($document);

        $this->assertStringNotContainsString('203.0.113.42', $encoded, 'the public IP from the logs and yabs ip_info leaked');
        $this->assertStringNotContainsString('acme-corp.internal', $encoded, 'the internal hostname leaked');
        $this->assertStringNotContainsString('Acme Broadband', $encoded, 'the ISP from yabs ip_info leaked');
        $this->assertStringNotContainsString('/var/www/html/preload.php', $encoded, 'the opcache.preload path leaked');

        $this->assertArrayNotHasKey('logs', $document);
        $this->assertArrayNotHasKey('target', $document['benchmarks']['http']);
        $this->assertArrayNotHasKey('url', $document['environment']['laravel']['environment']);
        $this->assertArrayNotHasKey('opcache.preload', $document['environment']['php']['ini']);
        $this->assertArrayNotHasKey('asn', $document['benchmarks']['cfspeedtest']);
        $this->assertArrayNotHasKey('colo', $document['benchmarks']['cfspeedtest']);
    }

    public function test_preloading_is_published_as_a_flag_rather_than_a_path(): void
    {
        $id = $this->seedSensitiveRun();

        $ini = $this->getJson("/runs/{$id}/submission")->json('document.environment.php.ini');

        $this->assertTrue($ini['opcache.preload_enabled']);
        // ini_get returns false for a directive that isn't set, which is not a
        // value worth publishing.
        $this->assertArrayNotHasKey('zend.assertions', $ini);
        $this->assertSame('tracing', $ini['opcache.jit']);
    }

    public function test_a_self_built_image_tag_cannot_carry_a_company_name(): void
    {
        $id = $this->seedSensitiveRun('20260806-205607-kitn', [
            'environment' => ['build_version' => 'ghcr.io/acme-corp/benchkit:latest'],
        ]);

        $document = $this->getJson("/runs/{$id}/submission")->json('document');

        $this->assertArrayNotHasKey('build_version', $document['environment']);
    }

    public function test_free_text_cost_is_normalized_into_the_structured_shape(): void
    {
        $id = $this->seedSensitiveRun();

        $cost = $this->getJson("/runs/{$id}/submission")->json('document.meta.cost');

        // Loose comparison: 48.0 comes back off the wire as 48.
        $this->assertEquals(['amount' => 48.0, 'currency' => 'USD', 'period' => 'monthly'], $cost);
    }

    public function test_a_legacy_snapshot_still_publishes_the_plan_the_submitter_typed(): void
    {
        // Runs saved before plan/datacenter were split held one plan_notes field.
        $id = $this->seedSensitiveRun('20260806-205610-kitq', [
            'meta' => ['label' => 'Legacy run', 'plan_notes' => 'Premium AMD 2GB'],
        ]);

        $this->getJson("/runs/{$id}/submission")
            ->assertJsonPath('document.meta.plan', 'Premium AMD 2GB');
    }

    public function test_malformed_rows_are_dropped_rather_than_published(): void
    {
        $id = $this->seedSensitiveRun();

        $document = $this->getJson("/runs/{$id}/submission")->json('document');

        $this->assertCount(1, $document['benchmarks']['php']['subjects']);
        $this->assertSame('CrudBenchmark', $document['benchmarks']['php']['subjects'][0]['benchmark']);
        $this->assertSame(['200' => 124814], $document['benchmarks']['http']['routes']['json']['status_codes']);
    }

    public function test_subjects_are_capped_so_a_full_run_cannot_grow_without_bound(): void
    {
        $subjects = array_map(fn (int $i) => [
            'benchmark' => 'CrudBenchmark',
            'subject' => "benchSubject{$i}",
            'mean_us' => 100.0 + $i,
        ], range(1, 250));

        $id = $this->seedSensitiveRun('20260806-205608-kito', [
            'benchmarks' => ['php' => ['headline' => ['read' => ['milliseconds' => 1.0]], 'subjects' => $subjects]],
        ]);

        $document = $this->getJson("/runs/{$id}/submission")->json('document');

        $this->assertCount(100, $document['benchmarks']['php']['subjects']);
    }

    public function test_hardware_results_are_flattened_out_of_the_raw_yabs_payload(): void
    {
        $id = $this->seedSensitiveRun();

        $this->getJson("/runs/{$id}/submission")
            ->assertJsonPath('document.benchmarks.geekbench.single', 2891)
            ->assertJsonPath('document.benchmarks.geekbench.version', '6')
            ->assertJsonPath('document.benchmarks.geekbench.url', 'https://browser.geekbench.com/v6/cpu/1234567')
            ->assertJsonPath('document.benchmarks.disk.0.bs', '4k')
            ->assertJsonMissingPath('document.benchmarks.yabs');
    }

    public function test_the_issue_leads_with_numbers_a_maintainer_can_read(): void
    {
        $id = $this->seedSensitiveRun();

        $response = $this->getJson("/runs/{$id}/submission")->assertOk();
        $body = urldecode(parse_url($response->json('issue_url'), PHP_URL_QUERY));

        $this->assertStringContainsString(SubmissionIssue::MARKER, $body);
        $this->assertStringContainsString('### fpm-nginx (Quick)', $body);

        // One fact per row rather than a run of middle dots: a label against a
        // value reads as a fact, a dot-separated string reads as one blob.
        $this->assertStringContainsString('| Host | DigitalOcean |', $body);
        $this->assertStringContainsString('| Plan | s-4vcpu-8gb |', $body);
        $this->assertStringContainsString('| Datacenter | nyc3 |', $body);
        $this->assertStringContainsString('| Cost | 48 USD/mo |', $body);
        $this->assertStringContainsString('| CPU | 4 vCPU |', $body);
        // The fixture reports GiB, a unit ServerSpecs doesn't emit — passed
        // through untouched rather than confidently relabelled as megabytes.
        $this->assertStringContainsString('| RAM | 7.8 GiB |', $body);
        $this->assertStringContainsString('| PHP | 8.5.0 (fpm-nginx) |', $body);
        $this->assertStringContainsString('| Laravel | 13.0.1 |', $body);

        // Right-aligned numeric columns, and no trailing zeros on the figures.
        $this->assertStringContainsString('| --- | ---: | ---: |', $body);
        $this->assertStringContainsString('| JSON API | 12,481.4 req/s | 2.14 ms |', $body);
    }

    public function test_the_title_says_what_was_benchmarked_how_and_where(): void
    {
        $id = $this->seedSensitiveRun();

        parse_str(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY), $query);

        $this->assertSame('Result: fpm-nginx (Quick) - DigitalOcean', $query['title']);
    }

    public function test_the_title_is_built_from_the_run_not_from_an_editable_label(): void
    {
        // Renaming a run must not cost the title the two facts that decide
        // whether two results can be compared: the variation and the preset.
        $id = $this->seedSensitiveRun('20260806-205614-kitu', [
            'meta' => ['label' => 'my cool box', 'provider' => null],
            'settings_preset' => 'full',
            'environment' => ['php' => ['php_version' => '8.5.0', 'php_variation' => 'frankenphp', 'octane' => true]],
        ]);

        parse_str(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY), $query);

        // A run with no host named is someone's own hardware, labelled the way
        // the gallery labels it.
        $this->assertSame('Result: frankenphp + octane (Full) - Self-Hosted', $query['title']);
    }

    public function test_the_title_stays_within_github_limits_however_long_the_host_details_are(): void
    {
        $id = $this->seedSensitiveRun('20260806-205615-kitv', [
            'meta' => ['label' => str_repeat('L', 500), 'provider' => str_repeat('P', 500)],
            'environment' => ['php' => ['php_variation' => str_repeat('V', 500)]],
        ]);

        parse_str(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY), $query);

        $this->assertLessThan(160, mb_strlen($query['title']));
    }

    public function test_the_issue_shows_throughput_and_setup_and_leaves_the_rest_to_the_token(): void
    {
        $id = $this->seedSensitiveRun();

        $body = urldecode(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY));

        // Two tables, deliberately. The secondary benchmarks sat in a third one
        // that repeated what the route table and the run's own label already
        // said; they still travel in the token and land in the pull request.
        $this->assertSame(2, substr_count($body, '| --- |'), 'the issue should carry exactly two tables');
        $this->assertStringNotContainsString('| Benchmark | Result |', $body);
        $this->assertStringNotContainsString('Geekbench', $body);
        $this->assertStringNotContainsString('Mbps', $body);

        // ...and the token still carries every one of them.
        $document = EncodeSubmissionToken::decode($this->getJson("/runs/{$id}/submission")->json('token'));

        $this->assertSame(2891, $document['benchmarks']['geekbench']['single']);
        $this->assertSame(940.2, $document['benchmarks']['cfspeedtest']['download_mbps']);
        $this->assertSame(12.5, $document['benchmarks']['php']['headline']['read']['milliseconds']);
    }

    public function test_environment_rows_appear_only_when_they_say_something(): void
    {
        $id = $this->seedSensitiveRun();

        $body = urldecode(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY));

        // Octane off and OPcache on are the expected case; a row that always
        // states the default is a row nobody reads.
        $this->assertStringNotContainsString('| Octane |', $body);
        $this->assertStringNotContainsString('| OPcache |', $body);

        // Disabled OPcache is an anomaly that changes how every number should
        // be read, so that one does get called out.
        $off = $this->seedSensitiveRun('20260806-205612-kits', [
            'environment' => ['php' => ['php_version' => '8.5.0', 'op_cache' => '0', 'octane' => true]],
        ]);

        $offBody = urldecode(parse_url($this->getJson("/runs/{$off}/submission")->json('issue_url'), PHP_URL_QUERY));

        $this->assertStringContainsString('| OPcache | Disabled |', $offBody);
        $this->assertStringContainsString('| Octane | Enabled |', $offBody);
    }

    public function test_memory_is_shown_in_the_unit_people_compare_hosts_in(): void
    {
        $id = $this->seedSensitiveRun('20260806-205613-kitt', [
            'environment' => ['server' => ['cpu_model' => 'X', 'cpu_cores' => 2, 'os' => 'Linux', 'ram' => '3181.102 MB']],
        ]);

        $body = urldecode(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY));

        $this->assertStringContainsString('| RAM | 3.1 GB |', $body);
    }

    public function test_the_token_rides_in_a_collapsed_block_that_says_what_it_is(): void
    {
        $id = $this->seedSensitiveRun();

        $response = $this->getJson("/runs/{$id}/submission")->assertOk();
        $body = urldecode(parse_url($response->json('issue_url'), PHP_URL_QUERY));

        $this->assertStringContainsString('<details><summary>Full results (compressed JSON output)</summary>', $body);
        $this->assertStringContainsString('```benchkit', $body);
        $this->assertStringContainsString($response->json('token'), $body);
        // An unreadable block in a public issue reads as something being hidden
        // unless it says otherwise, and links somewhere that explains it.
        $this->assertStringContainsString('does NOT include any private information', $body);
        $this->assertStringContainsString(SubmissionIssue::DOCS_URL, $body);
    }

    public function test_rows_for_things_this_run_has_no_answer_for_are_left_out(): void
    {
        $id = $this->seedSensitiveRun('20260806-205611-kitr', [
            'meta' => ['label' => 'Bare run'],
            'benchmarks' => ['http' => ['routes' => ['json' => [
                'requests_per_second' => 1000.0, 'p50_ms' => 1.0, 'p95_ms' => 2.0, 'p99_ms' => 3.0,
            ]]]],
        ]);

        $body = urldecode(parse_url($this->getJson("/runs/{$id}/submission")->json('issue_url'), PHP_URL_QUERY));

        $this->assertStringContainsString('| JSON API | 1,000 req/s | 2 ms |', $body);
        // No placeholder rows: the table's length is itself a signal of how
        // much the submitter told us, so a dash would be worse than a gap.
        $this->assertStringNotContainsString('| DB read |', $body);
        $this->assertStringNotContainsString('| Host |', $body);
        $this->assertStringNotContainsString('| Plan |', $body);
        $this->assertStringNotContainsString('| Cost |', $body);
    }

    public function test_a_token_too_long_to_prefill_falls_back_to_a_paste(): void
    {
        // Incompressible text, so the token stays over the URL ceiling however
        // well deflate does on the rest of the document.
        $id = $this->seedSensitiveRun('20260806-205609-kitp', [
            'meta' => ['label' => Str::random(20000)],
        ]);

        $response = $this->getJson("/runs/{$id}/submission")->assertOk();
        $url = $response->json('issue_url');

        $this->assertFalse($response->json('prefill'));
        $this->assertStringNotContainsString($response->json('token'), $url);
        // The marker still has to arrive or the bot never sees the issue.
        $this->assertStringContainsString(rawurlencode(SubmissionIssue::MARKER), $url);
        $this->assertStringContainsString(rawurlencode('Paste your submission token'), $url);
    }

    public function test_submission_returns_404_for_an_unknown_run(): void
    {
        $this->getJson('/runs/20990101-000000-zzzz/submission')->assertNotFound();
    }
}
