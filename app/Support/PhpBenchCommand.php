<?php

namespace App\Support;

use App\Actions\Results\PhpBenchmarkResults;

/**
 * Builds the phpbench command line for the PHP benchmark stage. Quick mode
 * limits the run to the headline CRUD subjects with fewer iterations so it
 * finishes in about a minute instead of the full ~30 minute suite, while
 * keeping the per-subject revs untouched so per-operation means stay
 * comparable between quick and full runs.
 */
class PhpBenchCommand
{
    public function build(string $mode): string
    {
        $command = sprintf(
            '%s run --report=comparison --output=csv',
            base_path('vendor/bin/phpbench')
        );

        if ($mode === 'quick') {
            foreach (PhpBenchmarkResults::headlineSubjects() as $spec) {
                $command .= sprintf(' --filter=%s', escapeshellarg("{$spec['benchmark']}::{$spec['subject']}$"));
            }

            $command .= ' --iterations=2 --warmup=1';
        }

        return sprintf('%s > %s', $command, escapeshellarg((new PhpBenchmarkResults)->path()));
    }
}
