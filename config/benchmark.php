<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Benchmark Results Path
    |--------------------------------------------------------------------------
    |
    | Directory where benchmark runs write their output files (yabs JSON,
    | phpbench CSV, cfspeedtest text). Overridable so tests can point at
    | a temporary directory.
    |
    */

    'results_path' => env('BENCHMARK_RESULTS_PATH', base_path('results')),

    /*
    |--------------------------------------------------------------------------
    | Run Session Path
    |--------------------------------------------------------------------------
    |
    | Directory holding the state of the run currently in progress: the run
    | record (run.json), its live console log (run.log), and the cancel
    | flag. A run is owned by a detached subprocess rather than by the
    | browser that started it, so this directory is how every tab — in any
    | browser, on any device — finds the live run, watches its output, and
    | cancels it. Overridable so tests can point at a temporary directory.
    |
    */

    'run_path' => env('BENCHMARK_RUN_PATH', storage_path('app/benchkit')),

    /*
    |--------------------------------------------------------------------------
    | PHP CLI Binary
    |--------------------------------------------------------------------------
    |
    | Used to spawn the detached benchmark process. PHP_BINARY is unusable
    | here because under FPM it points at the FPM binary and under FrankenPHP
    | at the FrankenPHP binary; neither runs Artisan. The container images
    | all ship a CLI `php` on PATH — set this only if yours does not.
    |
    */

    'php_binary' => env('BENCHMARK_PHP_BINARY', 'php'),

    /*
    |--------------------------------------------------------------------------
    | PHP Variation
    |--------------------------------------------------------------------------
    |
    | The serversideup/php image variation this instance was built from
    | (e.g. fpm-nginx, frankenphp). Baked into the Docker image as an ENV
    | at build time; null on non-Docker environments.
    |
    */

    'php_variation' => env('PHP_VARIATION'),

    /*
    |--------------------------------------------------------------------------
    | Web Server Load Test
    |--------------------------------------------------------------------------
    |
    | Each route is measured three ways, because one test cannot answer two
    | different questions. A discarded warmup leaves the worker pool and
    | OPcache warm. A sweep then measures throughput at several concurrency
    | levels, which is how much the server can take. Finally an open-loop pass
    | offers a rate below the peak the sweep found and times the answers, which
    | is what a visitor actually experiences.
    |
    | The concurrency levels are not configured. They are derived per host from
    | its core count and its worker count (App\Support\Http\LoadProfile), and
    | that is the point: a fixed connection count is twelve times oversubscribed
    | on a small box and barely warm on a large one, so it is not the same test
    | on two machines. Deriving them is what makes one setting fit every host
    | and keeps runs comparable.
    |
    | io_ms is the only load parameter left. It is the delay the /bench/io route
    | sleeps to model one outbound dependency call — the route where PHP-FPM
    | and worker mode converge, so users can see worker mode's lead shrink as
    | I/O grows. It changes what that one route measures rather than how hard
    | the load pushes, and a non-standard value is disclosed with the results.
    |
    | The target URL is normally auto-detected (loopback first, APP_URL as
    | a fallback). Set BENCHMARK_HTTP_URL only when the app can't reach
    | itself on a standard port.
    |
    */

    'http' => [
        'url' => env('BENCHMARK_HTTP_URL'),
        'io_ms' => 100,

        'sweep' => [
            'warmup_seconds' => 3,
            'level_seconds' => 6,
            'latency_seconds' => 10,
            // Busy enough to be realistic, with enough margin that a slightly
            // lucky sweep window does not produce a rate the server misses.
            'latency_load' => 0.70,
        ],
    ],

];
