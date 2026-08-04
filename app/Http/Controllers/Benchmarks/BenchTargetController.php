<?php

namespace App\Http\Controllers\Benchmarks;

use App\Http\Controllers\Controller;
use App\Support\BenchmarkHttpItems;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Target endpoints for the HTTP self-test. Each represents a typical
 * Laravel response shape (static, JSON API, database-backed) and is served
 * without session middleware so the load test measures the framework
 * request path, not session bookkeeping.
 */
class BenchTargetController extends Controller
{
    /**
     * Sentinel body for the static target. HttpBenchmarkTarget verifies this
     * exact body when resolving a target, so a web server answering 200 with
     * the wrong content (e.g. Caddy's empty default response) is rejected.
     */
    public const STATIC_BODY = 'BenchKit OK';

    public function staticResponse(): Response
    {
        return response(self::STATIC_BODY);
    }

    public function json(): JsonResponse
    {
        $items = [];
        for ($i = 1; $i <= 25; $i++) {
            $items[] = [
                'id' => $i,
                'name' => "Item {$i}",
                'value' => ($i * 7) % 100,
            ];
        }

        return response()->json([
            'status' => 'ok',
            'items' => $items,
        ]);
    }

    public function dbRead(): JsonResponse
    {
        try {
            $items = $this->queryItems();
        } catch (QueryException) {
            BenchmarkHttpItems::ensure();
            $items = $this->queryItems();
        }

        return response()->json([
            'status' => 'ok',
            'items' => $items,
        ]);
    }

    /**
     * Models a request whose time is dominated by one outbound dependency
     * call — a database over a socket, a cache, an HTTP API — by sleeping a
     * caller-supplied delay. This is the route where PHP-FPM and Octane
     * worker mode converge: the per-request framework bootstrap that worker
     * mode removes shrinks to noise once real I/O dominates the request. The
     * delay is clamped to the same 0–1000ms band the settings enforce, so a
     * stray query string can never hang a worker.
     */
    public function io(Request $request): JsonResponse
    {
        $ms = max(0, min(1000, $request->integer('ms', 100)));

        usleep($ms * 1000);

        return response()->json([
            'status' => 'ok',
            'io_ms' => $ms,
        ]);
    }

    protected function queryItems(): Collection
    {
        return DB::table(BenchmarkHttpItems::TABLE)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(20)
            ->get();
    }
}
