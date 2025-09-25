<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\Benchmarks\YabsController;

Route::get('/', [BenchmarkController::class, 'index']);
Route::get('/running', [BenchmarkController::class, 'running']);

Route::get('/yabs', [YabsController::class, 'index']);



