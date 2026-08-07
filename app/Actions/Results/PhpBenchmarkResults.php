<?php

namespace App\Actions\Results;

use League\Csv\Exception;
use League\Csv\Reader;

class PhpBenchmarkResults extends BenchmarkResults
{
    /**
     * The phpbench subjects surfaced as headline CRUD metrics. Every subject
     * operates on 100 records so the four numbers are comparable — which holds
     * only while each one measures its operation and nothing else. A subject
     * that sets up or restores state inside its timed body breaks the
     * comparison silently: delete used to rebuild the rows it removed, so it
     * reported 2.4x its real cost and read as the slowest operation.
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
            'label' => 'Delete 100 records (individual queries)',
        ],
    ];

    /**
     * @return array<string, array{benchmark: string, subject: string, records: int, label: string}>
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
     * @return array{headline: array<string, array{milliseconds: float|null, records: int, label: string}>, subjects: array<int, array{benchmark: string, subject: string, mean_us: float}>}|null
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
                'label' => $spec['label'],
            ];
        }

        $subjects = [];

        try {
            $reader = Reader::createFromPath($this->path());
            $reader->setHeaderOffset(0);
            $reader->setEscape('');

            foreach ($reader->getRecords() as $record) {
                $subjects[] = [
                    'benchmark' => $record['benchmark'],
                    'subject' => $record['subject'],
                    'mean_us' => (float) $record['mean'],
                ];

                foreach (self::HEADLINE_SUBJECTS as $key => $spec) {
                    if ($record['benchmark'] === $spec['benchmark'] && $record['subject'] === $spec['subject']) {
                        $headline[$key]['milliseconds'] = round($record['mean'] / 1000, 3);
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
}
