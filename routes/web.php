<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\EventController;

Route::get('/', [BenchmarkController::class, 'index']);
Route::post('/benchmark', [BenchmarkController::class, 'store']);

Route::get('/events', [EventController::class, 'index']);



