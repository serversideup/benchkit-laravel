<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureSqliteDatabaseExists();
    }

    /**
     * The app must boot with zero configuration (no .env): nothing persists
     * domain data to the database, but the PHP benchmark stage and the
     * /bench/db-read route still need a working SQLite connection, and the
     * default database file is gitignored — so it won't exist on a fresh
     * install unless something creates it.
     */
    protected function ensureSqliteDatabaseExists(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $database = config('database.connections.sqlite.database');

        if ($database === ':memory:' || file_exists($database)) {
            return;
        }

        File::ensureDirectoryExists(dirname($database));
        File::put($database, '');
    }
}
