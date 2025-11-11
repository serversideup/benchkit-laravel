<?php

namespace App\Actions\Benchmarks;

use Symfony\Component\Process\Process;

class YabsBenchmark
{
    public function execute( callable $outputCallback ): array
    {
        $bin = base_path('vendor/bin/yabs');
        $results = base_path('yabs-results.json');

        $command = sprintf(
            'script -q /dev/null -c %s',
            escapeshellarg(sprintf('%s -i -6 -w %s', $bin, $results))
        );

        $process = Process::fromShellCommandline($command, base_path(), null, null, null);

        // Start the process (non-blocking)
        $process->start();

        $process->wait(function ($type, $buffer) use ($outputCallback) {
            $buffer = trim($buffer);
            if ($buffer !== '') {
                $outputCallback([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'type' => $type,
                    'output' => $buffer,
                ]);
            }
        });

        return [
            'status' => $process->isSuccessful() ? 'completed' : 'error',
            'error' => $process->isSuccessful() ? null : $process->getExitCodeText(),
        ];
    }
}