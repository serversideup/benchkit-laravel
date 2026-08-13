<?php

namespace App\Actions\Results;

use League\Csv\Exception;
use League\Csv\Reader;

class PhpBenchmarkResults extends BenchmarkResults
{
    /**
     * Above this relative standard deviation, a mean stops describing the
     * distribution it came from and the UI says so rather than printing it to
     * four significant figures. Kept in step with HIGH_VARIANCE_RSTDEV in
     * resources/js/Composables/useRunSummary.js and its twin on the docs site.
     */
    public const HIGH_VARIANCE_RSTDEV = 10.0;

    /**
     * The phpbench subjects surfaced as headline CRUD metrics.
     *
     * The four tiles share a bar scale, so they have to measure the same unit
     * of work: 100 statements, one row each, addressed the same way. Two
     * things have broken that in the past and both are worth stating, because
     * neither is visible in a number.
     *
     * A subject that sets up or restores state inside its timed body reports
     * work it did not do — delete used to rebuild the rows it removed, and
     * read as the slowest operation at 2.4x its real cost.
     *
     * A subject that measures a different shape of work is worse, because it
     * looks right. Read was benchSelectWithLimit: one SELECT returning 100
     * rows, roughly a hundredth of the work of 100 individual statements,
     * printed on the same scale as them. It is now one SELECT per record.
     *
     * There is deliberately no display label here. Every surface that renders
     * these builds its own — the run page and the community gallery each hold
     * their own table of names, because a label belongs to the page showing it,
     * not to the measurement. Carrying one in the document meant shipping a
     * sentence per operation, in every submission, that nothing ever read.
     *
     * @var array<string, array{benchmark: string, subject: string, records: int, statements: int}>
     */
    protected const HEADLINE_SUBJECTS = [
        'create' => [
            'benchmark' => 'InsertBenchmark',
            'subject' => 'benchDbFacadeInsertIndividual',
            'records' => 100,
            'statements' => 100,
        ],
        'read' => [
            'benchmark' => 'QueryBenchmark',
            'subject' => 'benchSelectIndividualById',
            'records' => 100,
            'statements' => 100,
        ],
        'update' => [
            'benchmark' => 'UpdateBenchmark',
            'subject' => 'benchQueryBuilderIndividual',
            'records' => 100,
            'statements' => 100,
        ],
        'delete' => [
            'benchmark' => 'DeleteBenchmark',
            'subject' => 'benchQueryBuilderIndividual',
            'records' => 100,
            'statements' => 100,
        ],
    ];

    /**
     * @return array<string, array{benchmark: string, subject: string, records: int, statements: int}>
     */
    public static function headlineSubjects(): array
    {
        return self::HEADLINE_SUBJECTS;
    }

    public function path(): string
    {
        return $this->resultsPath('phpbench_results.csv');
    }

    /**
     * @return array{headline: array<string, array<string, mixed>>, subjects: array<int, array<string, mixed>>}|null
     */
    public function execute(): ?array
    {
        if (! file_exists($this->path())) {
            return null;
        }

        $headline = [];

        foreach (self::HEADLINE_SUBJECTS as $key => $spec) {
            $headline[$key] = [
                'milliseconds' => null,
                'records' => $spec['records'],
                'statements' => $spec['statements'],
                'best_ms' => null,
                'worst_ms' => null,
                'rstdev' => null,
                'iterations' => null,
                'revolutions' => null,
            ];
        }

        $subjects = [];

        try {
            $reader = Reader::createFromPath($this->path());
            $reader->setHeaderOffset(0);
            $reader->setEscape('');

            foreach ($reader->getRecords() as $record) {
                $subjects[] = $this->subjectFrom($record);

                foreach (self::HEADLINE_SUBJECTS as $key => $spec) {
                    if ($record['benchmark'] === $spec['benchmark'] && $record['subject'] === $spec['subject']) {
                        $headline[$key] = array_merge($headline[$key], $this->headlineFrom($record));
                    }
                }
            }
        } catch (Exception) {
            return null;
        }

        return [
            'headline' => $headline,
            'subjects' => $subjects,
        ];
    }

    /**
     * One row of the phpbench comparison report, in the microseconds per
     * revolution it was measured in. The spread columns travel with the mean
     * because a mean on its own cannot be checked: quick mode averages a
     * handful of iterations, and one stalled iteration moves the headline
     * without leaving a trace anywhere else in the document.
     *
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    protected function subjectFrom(array $record): array
    {
        return [
            'benchmark' => $record['benchmark'],
            'subject' => $record['subject'],
            'mean_us' => (float) $record['mean'],
            'best_us' => $this->numeric($record, 'best'),
            'worst_us' => $this->numeric($record, 'worst'),
            'stdev_us' => $this->numeric($record, 'stdev'),
            'rstdev' => $this->rounded($this->numeric($record, 'rstdev'), 2),
            'revolutions' => $this->integer($record, 'revs'),
            'iterations' => $this->integer($record, 'its'),
        ];
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    protected function headlineFrom(array $record): array
    {
        return [
            'milliseconds' => $this->rounded((float) $record['mean'] / 1000, 3),
            'best_ms' => $this->rounded($this->milliseconds($record, 'best'), 3),
            'worst_ms' => $this->rounded($this->milliseconds($record, 'worst'), 3),
            'rstdev' => $this->rounded($this->numeric($record, 'rstdev'), 2),
            'iterations' => $this->integer($record, 'its'),
            'revolutions' => $this->integer($record, 'revs'),
        ];
    }

    /**
     * Columns are read defensively: the report is configured in phpbench.json,
     * and a results file written by an older configuration is still worth
     * reading for the means it does have.
     *
     * @param  array<string, string>  $record
     */
    protected function numeric(array $record, string $column): ?float
    {
        $value = $record[$column] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, string>  $record
     */
    protected function integer(array $record, string $column): ?int
    {
        $value = $this->numeric($record, $column);

        return $value === null ? null : (int) $value;
    }

    /**
     * @param  array<string, string>  $record
     */
    protected function milliseconds(array $record, string $column): ?float
    {
        $value = $this->numeric($record, $column);

        return $value === null ? null : $value / 1000;
    }

    protected function rounded(?float $value, int $precision): ?float
    {
        return $value === null ? null : round($value, $precision);
    }
}
