<?php

namespace App\Actions\Specs;

use Illuminate\Support\Facades\Artisan;

class LaravelSpecs
{
    public function execute()
    {
        Artisan::call('about --json');
        return Artisan::output();
    }
}