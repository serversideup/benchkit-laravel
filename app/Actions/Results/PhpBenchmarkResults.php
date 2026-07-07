<?php

namespace App\Actions\Results;

use League\Csv\Reader;

class PhpBenchmarkResults
{
    /**
     * The phpbench subjects surfaced as headline CRUD metrics. Every subject
     * operates on 100 records so the four numbers are comparable.
     *
     * @var array<string, array{benchmark: string, subject: string, records: int, label: string}>
     */
    protected const HEADLINE_SUBJECTS = [
        'create' => [
            'benchmark' => 'InsertBenchmark',
            'subject' => 'benchDbFacadeInsertIndividual',
            'records' => 100,
            'label' => 'Insert 100 records (individual queries)',
        ],
        'read' => [
            'benchmark' => 'QueryBenchmark',
            'subject' => 'benchSelectWithLimit',
            'records' => 100,
            'label' => 'Select 100 records (indexed where + limit)',
        ],
        'update' => [
            'benchmark' => 'UpdateBenchmark',
            'subject' => 'benchQueryBuilderIndividual',
            'records' => 100,
            'label' => 'Update 100 records (individual queries)',
        ],
        'delete' => [
            'benchmark' => 'DeleteBenchmark',
            'subject' => 'benchQueryBuilderIndividual',
            'records' => 100,
            'label' => 'Delete 100 records (individual queries, includes restoring rows)',
        ],
    ];

    public function path(): string
    {
        return config('benchmark.results_path').'/phpbench_results.csv';
    }

    /**
     * @return array{headline: array<string, array{milliseconds: float|null, records: int, label: string}>, subjects: array<int, array{benchmark: string, subject: string, mean_us: float}>}|null
     */
    public function execute(): ?array
    {
        if (! file_exists($this->path())) {
            return null;
        }

        $reader = Reader::createFromPath($this->path());
        $reader->setHeaderOffset(0);
        $reader->setEscape('');

        $headline = [];

        foreach (self::HEADLINE_SUBJECTS as $key => $spec) {
            $headline[$key] = [
                'milliseconds' => null,
                'records' => $spec['records'],
                'label' => $spec['label'],
            ];
        }

        $subjects = [];

        foreach ($reader->getRecords() as $record) {
            $subjects[] = [
                'benchmark' => $record['benchmark'],
                'subject' => $record['subject'],
                'mean_us' => (float) $record['mean'],
            ];

            foreach (self::HEADLINE_SUBJECTS as $key => $spec) {
                if ($record['benchmark'] === $spec['benchmark'] && $record['subject'] === $spec['subject']) {
                    $headline[$key]['milliseconds'] = round($record['mean'] / 1000, 1);
                }
            }
        }

        return [
            'headline' => $headline,
            'subjects' => $subjects,
        ];
    }
}
