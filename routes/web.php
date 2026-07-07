<?php

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\CloudflareSpeedTestController;
use App\Http\Controllers\Benchmarks\HttpBenchmarkController;
use App\Http\Controllers\Benchmarks\PhpBenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;
use App\Http\Controllers\ResultsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BenchmarkController::class, 'index']);

Route::get('/results', [ResultsController::class, 'index']);

Route::post('/yabs', [YabsController::class, 'index']);
Route::get('/yabs/results', [YabsController::class, 'results']);

Route::post('/cfspeedtest', [CloudflareSpeedTestController::class, 'index']);
Route::get('/cfspeedtest/results', [CloudflareSpeedTestController::class, 'results']);

Route::post('/http', [HttpBenchmarkController::class, 'index']);
Route::get('/http/results', [HttpBenchmarkController::class, 'results']);

Route::post('/php', [PhpBenchmarkController::class, 'index']);
Route::get('/php/results', [PhpBenchmarkController::class, 'results']);
