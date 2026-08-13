<?php

namespace App\Support;

use App\Actions\Results\PhpBenchmarkResults;

/**
 * Builds the phpbench command line for the PHP benchmark stage. Quick mode
 * limits the run to the headline CRUD subjects with fewer iterations so it
 * finishes in about a minute instead of the full ~30 minute suite.
 *
 * Iterations are the only thing it changes. Revs and warmup stay at whatever
 * the subject declares, because those two decide what a measurement *is* — a
 * quick run that measured different work than a full one would be worse than
 * no quick run, since both land in the same gallery under the same numbers.
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

            // Iterations only. Warmup is deliberately left at the subject's own
            // Warmup(0) — overriding it made quick and full measure different
            // work, because a warmup revolution mutates the fixture the
            // measurement is supposed to see. See BaseBenchmark::prime().
            $command .= sprintf(' --iterations=%d', self::QUICK_ITERATIONS);
        }

        return sprintf('%s > %s', $command, escapeshellarg((new PhpBenchmarkResults)->path()));
    }
}
