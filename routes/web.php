<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;
use App\Http\Controllers\Benchmarks\CloudflareSpeedTestController;
use App\Http\Controllers\Benchmarks\PhpBenchmarkController;

Route::get('/', [BenchmarkController::class, 'index']);

Route::get('/yabs', [YabsController::class, 'index']);
Route::get('/yabs/results', [YabsController::class, 'results']);

Route::get('/cfspeedtest', [CloudflareSpeedTestController::class, 'index']);

Route::get('/php', [PhpBenchmarkController::class, 'index']);
Route::get('/php/results', [PhpBenchmarkController::class, 'results']);
