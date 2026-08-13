<?php

use App\Http\Controllers\Benchmarks\BenchTargetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HTTP Benchmark Target Routes
|--------------------------------------------------------------------------
|
| Registered without the web middleware group (no session, no cookies, no
| CSRF) so the HTTP self-test measures the framework request path without
| accumulating session state under load.
|
*/

Route::get('/bench/static', [BenchTargetController::class, 'staticResponse']);
Route::get('/bench/json', [BenchTargetController::class, 'json']);
Route::get('/bench/db-read', [BenchTargetController::class, 'dbRead']);
Route::get('/bench/io', [BenchTargetController::class, 'io']);

/*
 * Not a load-test target. This is how the run finds out what PHP looks like in
 * the process that serves requests, rather than in the CLI process assembling
 * the results. It lives here because it has to answer on the same origin the
 * load test measures — that is the whole point of it.
 */
Route::get('/bench/env', [BenchTargetController::class, 'environment']);
