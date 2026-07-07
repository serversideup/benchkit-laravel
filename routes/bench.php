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
