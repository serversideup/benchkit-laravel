<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves the base URL the HTTP self-test should load-test. Prefers the
 * container-internal ports (loopback keeps the request path identical
 * across image variations and bypasses external proxies), falling back to
 * APP_URL for environments like Laravel Cloud where the app can only
 * reach itself through its public URL.
 */
class HttpBenchmarkTarget
{
    /**
     * @return array{url: string, mode: string}|null
     */
    public function resolve(): ?array
    {
        foreach ($this->candidates() as $candidate) {
            if ($this->responds($candidate['url'])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{url: string, mode: string}>
     */
    protected function candidates(): array
    {
        $candidates = [];

        if ($configured = config('benchmark.http.url')) {
            $candidates[] = ['url' => rtrim($configured, '/'), 'mode' => 'custom'];
        }

        $candidates[] = ['url' => 'http://localhost:8080', 'mode' => 'loopback'];
        $candidates[] = ['url' => 'https://localhost:8443', 'mode' => 'loopback'];

        if ($appUrl = config('app.url')) {
            $candidates[] = ['url' => rtrim($appUrl, '/'), 'mode' => 'app-url'];
        }

        return $candidates;
    }

    /**
     * A candidate only qualifies when it answers the benchmark route
     * directly — redirects are rejected so the load test never measures a
     * redirect chain instead of the application.
     */
    protected function responds(string $baseUrl): bool
    {
        try {
            return Http::withoutRedirecting()
                ->withOptions(['verify' => false])
                ->timeout(3)
                ->get($baseUrl.'/bench/static')
                ->status() === 200;
        } catch (Throwable) {
            return false;
        }
    }
}
