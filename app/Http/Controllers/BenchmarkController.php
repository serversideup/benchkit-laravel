<?php

namespace App\Http\Controllers;

use App\Actions\Runs\ListRuns;
use App\Actions\Specs\LaravelSpecs;
use App\Actions\Specs\PhpSpecs;
use App\Actions\Specs\ServerSpecs;
use Inertia\Inertia;
use Inertia\Response;

class BenchmarkController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Index', [
            'server' => (new ServerSpecs)->execute(),
            'php' => (new PhpSpecs)->execute(),
            'laravel' => (new LaravelSpecs)->execute(),
            'recentRuns' => array_slice((new ListRuns)->execute(), 0, 3),
        ]);
    }
}
