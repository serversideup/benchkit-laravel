<?php

namespace App\Http\Controllers\Benchmarks;

use App\Http\Controllers\Controller;
use League\Csv\Reader;

class PhpBenchmarkController extends Controller
{
    public function index()
    {
        // spin exec php vendor/bin/phpbench run --report=comparison --output=csv > phpbench_results.csv
        return response()->stream(function () {
            while (ob_get_level()) {
                ob_end_flush();
            }
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            set_time_limit(0);

            echo "retry: 2000\n\n"; // keep connection healthy
            @ob_flush(); flush();

            $lastHeartbeat = time();
            $outputCallback = function ($data) use (&$lastHeartbeat) {
                echo "data: " . json_encode($data) . "\n\n";
                @ob_flush(); flush();
            };

            $processStarted = false;
            $result = null;

             // We need to manually handle the process to allow heartbeats
            $bin = base_path('vendor/bin/phpbench');
            $command = sprintf(
                'script -q /dev/null -c %s',
                escapeshellarg(sprintf('%s run --report=comparison --output=csv > results/phpbench_results.csv', $bin))
            );

            $process = \Symfony\Component\Process\Process::fromShellCommandline(
                $command, 
                base_path(), 
                null, 
                null, 
                null
            );

            $process->start();
            $processStarted = true;

            // Loop while process is running, sending heartbeats every 30 seconds
            while ($process->isRunning()) {
                // Check for process output
                $output = $process->getIncrementalOutput();
                $errorOutput = $process->getIncrementalErrorOutput();
                
                if ($output !== '') {
                    $lines = explode("\n", trim($output));
                    foreach ($lines as $line) {
                         // Remove ANSI escape sequences
                        $text = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $line);
                        // Remove other control characters except newlines and tabs
                        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

                        if (trim($line) !== '') {
                            $outputCallback([
                                'timestamp' => date('Y-m-d H:i:s'),
                                'type' => 'out',
                                'output' => $text,
                            ]);
                        }
                    }
                }

                if ($errorOutput !== '') {
                    $lines = explode("\n", trim($errorOutput));
                    foreach ($lines as $line) {
                        if (trim($line) !== '') {
                            $outputCallback([
                                'timestamp' => date('Y-m-d H:i:s'),
                                'type' => 'err',
                                'output' => $line,
                            ]);
                        }
                    }
                }
                
                // Send heartbeat every 30 seconds
                if (time() - $lastHeartbeat >= 30) {
                    echo "data: " . json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => 'heartbeat',
                        'output' => 'Connection alive',
                    ]) . "\n\n";
                    @ob_flush(); flush();
                    $lastHeartbeat = time();
                }
                
                // Small sleep to prevent CPU spinning
                usleep(100000); // 0.1 seconds
            }

            // Process any remaining output
            $remainingOutput = $process->getOutput();
            $remainingError = $process->getErrorOutput();
            
            if ($remainingOutput !== '') {
                $lines = explode("\n", trim($remainingOutput));
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $outputCallback([
                            'timestamp' => date('Y-m-d H:i:s'),
                            'type' => 'out',
                            'output' => $line,
                        ]);
                    }
                }
            }

            $result = [
                'status' => $process->isSuccessful() ? 'completed' : 'error',
                'error' => $process->isSuccessful() ? null : $process->getExitCodeText(),
            ];

            echo "data: " . json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => $result['status'],
                'error' => $result['error'],
            ]) . "\n\n";
            @ob_flush(); flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function results()
    {
        $reader = Reader::createFromPath(base_path('results/phpbench_results.csv'));
        $reader->setHeaderOffset(0);
        $reader->setEscape('');

        $records = $reader->getRecords();
        
        $create = '';
        $read = '';
        $update = '';
        $delete = '';

        foreach ($records as $record) {
            if( $record['benchmark'] === 'QueryBenchmark' && $record['subject'] === 'benchSimpleSelect' ) {
                // The 'mean' value is in microseconds, convert it to milliseconds (1 ms = 1000 us)
                $read = round($record['mean'] / 1000, 0);
            }

            if( $record['benchmark'] === 'InsertBenchmark' && $record['subject'] === 'benchDbFacadeInsertIndividual' ) {
                $create = round($record['mean'] / 1000, 0);
            }

            if( $record['benchmark'] === 'UpdateBenchmark' && $record['subject'] === 'benchQueryBuilderIndividual' ) {
                $update = round($record['mean'] / 1000, 0);
            }

            if( $record['benchmark'] === 'DeleteBenchmark' && $record['subject'] === 'benchQueryBuilderIndividual' ) {
                $delete = round($record['mean'] / 1000, 0);
            }
        }
        
        return response()->json([
            'phpbench_results' => [
                'create' => $create,
                'read' => $read,
                'update' => $update,
                'delete' => $delete,
            ]
        ]);
    }
}