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
    /**
     * Iterations quick mode measures each headline subject over.
     *
     * Two — what quick mode used to run — is enough to print a mean and not
     * enough to describe one: a single stalled iteration moved the headline by
     * its own magnitude and nothing downstream could tell. Five is the point
     * where the reported standard deviation starts to mean something, and it
     * costs almost nothing here, because every headline subject runs one
     * revolution of 100 statements.
     */
    public const QUICK_ITERATIONS = 5;

    public const QUICK_WARMUP = 1;

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

            $command .= sprintf(' --iterations=%d --warmup=%d', self::QUICK_ITERATIONS, self::QUICK_WARMUP);
        }

        return sprintf('%s > %s', $command, escapeshellarg((new PhpBenchmarkResults)->path()));
    }
}
