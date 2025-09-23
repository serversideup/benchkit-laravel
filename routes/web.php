<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BenchmarkController;

Route::get('/', [BenchmarkController::class, 'index']);
