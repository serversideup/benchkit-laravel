<?php

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\CloudflareSpeedTestController;
use App\Http\Controllers\Benchmarks\HttpBenchmarkController;
use App\Http\Controllers\Benchmarks\PhpBenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BenchmarkController::class, 'index']);

Route::get('/results', [ResultsController::class, 'index']);

Route::get('/runs', [RunController::class, 'index']);
Route::post('/runs', [RunController::class, 'store']);
Route::get('/runs/{id}', [RunController::class, 'show'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::get('/runs/{id}/submission', [RunController::class, 'submission'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::patch('/runs/{id}', [RunController::class, 'update'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::delete('/runs/{id}', [RunController::class, 'destroy'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::get('/compare/{a}/{b}', [RunController::class, 'compare'])
    ->where('a', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}')
    ->where('b', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');

// The run in progress. Stages are no longer started individually by the
// browser: one detached process runs the whole queue, and these endpoints
// start it, follow its console, and stop it — from any client.
Route::post('/run', [RunSessionController::class, 'store']);
Route::get('/run/log', [RunSessionController::class, 'log']);
Route::post('/run/cancel', [RunSessionController::class, 'cancel']);
Route::post('/run/save', [RunSessionController::class, 'save']);
Route::delete('/run', [RunSessionController::class, 'destroy']);

Route::get('/yabs/results', [YabsController::class, 'results']);
Route::get('/cfspeedtest/results', [CloudflareSpeedTestController::class, 'results']);
Route::get('/http/results', [HttpBenchmarkController::class, 'results']);
Route::get('/php/results', [PhpBenchmarkController::class, 'results']);
