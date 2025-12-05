<?php

namespace App\Http\Controllers\Benchmarks;

use App\Http\Controllers\Controller;

class CloudflareSpeedTestController extends Controller
{
    public function index()
    {
        // ./vendor/bin/cfspeedtest --ipv4
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
            $bin = base_path('vendor/bin/cfspeedtest');
            $command = sprintf(
                'script -q /dev/null -c %s',
                escapeshellarg(sprintf('%s --ipv4', $bin))
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
                        if (trim($line) !== '') {
                            $outputCallback([
                                'timestamp' => date('Y-m-d H:i:s'),
                                'type' => 'out',
                                'output' => $line,
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
}