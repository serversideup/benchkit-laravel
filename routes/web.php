<?php

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\CloudflareSpeedTestController;
use App\Http\Controllers\Benchmarks\HttpBenchmarkController;
use App\Http\Controllers\Benchmarks\PhpBenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\RunController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BenchmarkController::class, 'index']);

Route::get('/results', [ResultsController::class, 'index']);

Route::get('/runs', [RunController::class, 'index']);
Route::post('/runs', [RunController::class, 'store']);
Route::get('/runs/{id}', [RunController::class, 'show'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::patch('/runs/{id}', [RunController::class, 'update'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::delete('/runs/{id}', [RunController::class, 'destroy'])->where('id', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');
Route::get('/compare/{a}/{b}', [RunController::class, 'compare'])
    ->where('a', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}')
    ->where('b', '[0-9]{8}-[0-9]{6}-[a-z0-9]{4}');

Route::post('/yabs', [YabsController::class, 'index']);
Route::get('/yabs/results', [YabsController::class, 'results']);

Route::post('/cfspeedtest', [CloudflareSpeedTestController::class, 'index']);
Route::get('/cfspeedtest/results', [CloudflareSpeedTestController::class, 'results']);

Route::post('/http', [HttpBenchmarkController::class, 'index']);
Route::get('/http/results', [HttpBenchmarkController::class, 'results']);

Route::post('/php', [PhpBenchmarkController::class, 'index']);
Route::get('/php/results', [PhpBenchmarkController::class, 'results']);
