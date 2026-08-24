<?php

namespace App\Http\Controllers;

use App\Support\GeneratorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mints an external-load pairing. This is the only place a token comes from,
 * and it sits behind the web middleware — CSRF and all — so only the UI can
 * create one; the token-addressed endpoints the generator itself talks to
 * live on the bench routes.
 */
class GeneratorSessionController extends Controller
{
    public function __construct(protected GeneratorSession $session) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // The browser's origin: the one URL proven reachable from outside
            // this machine, because the browser is outside. A configured
            // BENCHMARK_HTTP_URL still outranks it.
            'target_url' => ['required', 'url:http,https', 'max:200'],
        ]);

        $target = rtrim(config('benchmark.http.url') ?: $validated['target_url'], '/');

        $this->session->create($target, $target);

        return response()->json(['generator' => $this->session->payload()], 201);
    }
}
