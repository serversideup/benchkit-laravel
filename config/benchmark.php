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
    | duration and connection count are intentionally fixed — consistent
    | loadgen settings across runs matter more than the specific values,
    | so results stay comparable between hosts and image variations.
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
