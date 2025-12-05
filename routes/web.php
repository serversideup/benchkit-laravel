<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;
use App\Http\Controllers\Benchmarks\CloudflareSpeedTestController;

Route::get('/', [BenchmarkController::class, 'index']);

Route::get('/yabs', [YabsController::class, 'index']);
Route::get('/cfspeedtest', [CloudflareSpeedTestController::class, 'index']);



