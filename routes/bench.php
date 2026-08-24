<?php

use App\Http\Controllers\Benchmarks\BenchTargetController;
use App\Http\Controllers\Benchmarks\GeneratorController;
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

/*
 * The external load generator's endpoints. Sessionless like everything else
 * here — the generator is a shell script on another machine with a token,
 * not a browser with a cookie. Every one of them 404s unless the token
 * matches the current pairing, and the whole surface only exists while a
 * pairing does. None of them are load-test targets, and the script never
 * calls them during a measured window.
 */
Route::middleware('throttle:generator')->group(function (): void {
    Route::get('/bench/generator/{token}/script', [GeneratorController::class, 'script']);
    Route::post('/bench/generator/{token}/handshake', [GeneratorController::class, 'handshake']);
    Route::get('/bench/generator/{token}/work', [GeneratorController::class, 'work']);
});

Route::post('/bench/generator/{token}/results/{route}', [GeneratorController::class, 'upload'])
    ->middleware('throttle:generator-upload')
    ->where('route', 'static|json|db-read|io');
