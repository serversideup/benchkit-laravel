<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class EventController extends Controller
{
    public function index()
    {
        return response()->stream(function () {
            while (ob_get_level()) {
                ob_end_flush();
            }
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            set_time_limit(0);
            
            $bin = base_path('vendor/bin/yabs');
            $results = base_path('yabs-results.json');

            $command = sprintf(
                'script -q /dev/null -c %s',
                escapeshellarg(sprintf('%s -i -6 -w %s', $bin, $results))
            );

            $process = Process::fromShellCommandline($command, base_path(), null, null, null);


            echo "retry: 2000\n\n"; // keep connection healthy
            @ob_flush(); flush();

            $process->run(function ($type, $buffer) {
                $buffer = trim($buffer);
                if ($buffer !== '') {
                    echo "data: " . json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => $type, // 'out' or 'err'
                        'output' => $buffer,
                    ]) . "\n\n";
                    @ob_flush(); flush();
                }
            });

            $status = $process->isSuccessful() ? 'completed' : 'error';
            $error  = $process->isSuccessful() ? null : $process->getExitCodeText();

            echo "data: " . json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => $status,
                'error' => $error,
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