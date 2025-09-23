<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Actions\Specs\ServerSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\LaravelSpecs;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BenchmarkController extends Controller
{
    public function index( Request $request )
    {
        return Inertia::render('Index', [
            'server' => Inertia::defer(fn () => ( new ServerSpecs() )->execute()),
            'php' => Inertia::defer(fn () => ( new PhpSpecs() )->execute()),
            'laravel' => Inertia::defer(fn () => ( new LaravelSpecs() )->execute()),
        ]);
    }
}