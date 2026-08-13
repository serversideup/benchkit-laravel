<?php

namespace App\Actions\Specs;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The PHP environment of the process that serves requests, fetched from the
 * running application over HTTP and kept for the results document.
 *
 * A run assembles its results in `php artisan benchmark:run`, so every
 * environment value it can read locally is the CLI SAPI's: opcache.enable_cli,
 * the CLI memory limit, php_sapi_name() of "cli". Those sat on the results page
 * beside a throughput number they had nothing to do with, and the FPM pool size
 * shown next to them was read from a config file rather than from the pool that
 * actually served the load. Asking the web server for its own answer is the
 * only way to get one that matches the numbers it produced.
 *
 * Best-effort by design. A run whose HTTP stage is off, or whose environment
 * endpoint is unreachable, still gets a full results document — it just carries
 * the CLI environment, labelled as such, instead of quietly implying otherwise.
 */
class WebRuntimeSpecs
{
    public const FILE = 'runtime.json';

    /**
     * The web server's environment is a fraction of a kilobyte of JSON, so a
     * short timeout is generous. This runs against a URL the target resolver
     * has already proved answers, immediately before the load test.
     */
    protected const TIMEOUT_SECONDS = 5;

    /**
     * Ask the running application what PHP looks like where it serves, and
     * record the answer for the results document.
     *
     * @return array<string, mixed>|null
     */
    public function capture(string $baseUrl): ?array
    {
        $runtime = $this->fetch($baseUrl);

        // Written or cleared on every capture. Leaving a previous run's file in
        // place would attach one machine's runtime to another machine's numbers,
        // which is a worse failure than having no runtime at all.
        if ($runtime === null) {
            $this->forget();

            return null;
        }

        file_put_contents($this->path(), json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $runtime;
    }

    /**
     * What the current run's HTTP stage recorded, or null when it did not run
     * or could not reach the endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        if (! file_exists($this->path())) {
            return null;
        }

        $runtime = json_decode((string) file_get_contents($this->path()), true);

        return $this->looksLikeRuntime($runtime) ? $runtime : null;
    }

    /** Drop any runtime left behind by an earlier run. */
    public function forget(): void
    {
        if (file_exists($this->path())) {
            unlink($this->path());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetch(string $baseUrl): ?array
    {
        try {
            $response = Http::withoutRedirecting()
                ->withOptions(['verify' => false])
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(rtrim($baseUrl, '/').'/bench/env');
        } catch (Throwable) {
            return null;
        }

        if ($response->status() !== 200) {
            return null;
        }

        $runtime = $response->json();

        return $this->looksLikeRuntime($runtime) ? $runtime : null;
    }

    /**
     * An older BenchKit build has no /bench/env and may answer the path with
     * something else entirely, so the response has to be recognisable as a
     * runtime before it is allowed to stand in for the local one.
     */
    protected function looksLikeRuntime(mixed $runtime): bool
    {
        return is_array($runtime)
            && is_string($runtime['php_version'] ?? null)
            && is_string($runtime['php_server_api'] ?? null);
    }

    protected function path(): string
    {
        return config('benchmark.results_path').'/'.self::FILE;
    }
}
