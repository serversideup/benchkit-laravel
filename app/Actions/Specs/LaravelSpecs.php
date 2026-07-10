<?php

namespace App\Actions\Specs;

use Illuminate\Support\Facades\Artisan;

class LaravelSpecs
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        Artisan::call('about --json');

        return json_decode(Artisan::output(), true) ?? [];
    }
}
