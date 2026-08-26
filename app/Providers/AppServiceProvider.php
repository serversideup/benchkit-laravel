<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureGeneratorRateLimits();
    }

    /**
     * The generator endpoints are unauthenticated and token-addressed, so a
     * wrong token 404s — throttling is what keeps guessing expensive. The
     * legitimate script polls every 2 seconds, well inside the limit; uploads
     * happen four times per run.
     */
    protected function configureGeneratorRateLimits(): void
    {
        RateLimiter::for('generator', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        // A run uploads one file per measured window rather than one per
        // route: four routes across six concurrency levels plus a
        // response-time pass is around twenty-eight, arriving roughly one
        // every six seconds. Twenty a minute would have throttled a healthy
        // generator into a timeout.
        RateLimiter::for('generator-upload', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
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
