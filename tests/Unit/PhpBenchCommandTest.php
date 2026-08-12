<?php

namespace Tests\Unit;

use App\Actions\Results\PhpBenchmarkResults;
use App\Support\PhpBenchCommand;
use Tests\TestCase;

class PhpBenchCommandTest extends TestCase
{
    public function test_full_mode_runs_the_entire_suite(): void
    {
        $command = (new PhpBenchCommand)->build('full');

        $this->assertStringContainsString('phpbench', $command);
        $this->assertStringContainsString('run --report=comparison --output=csv', $command);
        $this->assertStringNotContainsString('--filter', $command);
        $this->assertStringNotContainsString('--iterations', $command);
        $this->assertStringNotContainsString('--warmup', $command);
    }

    public function test_quick_mode_filters_to_the_headline_subjects_with_reduced_iterations(): void
    {
        $command = (new PhpBenchCommand)->build('quick');

        $this->assertSame(count(PhpBenchmarkResults::headlineSubjects()), substr_count($command, '--filter='));

        foreach (PhpBenchmarkResults::headlineSubjects() as $spec) {
            $this->assertStringContainsString(
                escapeshellarg("{$spec['benchmark']}::{$spec['subject']}$"),
                $command
            );
        }

        $this->assertStringContainsString('--iterations=5', $command);
        $this->assertStringContainsString('--warmup=1', $command);
    }

    public function test_both_modes_write_results_to_the_expected_path(): void
    {
        $path = escapeshellarg((new PhpBenchmarkResults)->path());

        $this->assertStringEndsWith("> {$path}", (new PhpBenchCommand)->build('full'));
        $this->assertStringEndsWith("> {$path}", (new PhpBenchCommand)->build('quick'));
    }
}
