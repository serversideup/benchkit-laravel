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
    | HTTP Self-Test
    |--------------------------------------------------------------------------
    |
    | Load generator configuration for the HTTP benchmark stage (oha). The
    | duration and connection count below define the "standard BenchKit
    | load" — the default every run shares so results stay comparable
    | between hosts and image variations. Users may override them per run
    | from the settings drawer (validated to 5-60s / 1-500 connections);
    | non-standard values are always disclosed alongside the results.
    |
    | The target URL is normally auto-detected (loopback first, APP_URL as
    | a fallback). Set BENCHMARK_HTTP_URL only when the app can't reach
    | itself on a standard port.
    |
    */

    'http' => [
        'url' => env('BENCHMARK_HTTP_URL'),
        'duration_seconds' => 10,
        'connections' => 50,
    ],

];
