<?php

namespace App\Http\Controllers\Benchmarks;

use App\Http\Controllers\Controller;
use App\Support\BenchmarkHttpItems;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
    public function staticResponse(): Response
    {
        return response('BenchKit OK');
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

    protected function queryItems(): Collection
    {
        return DB::table(BenchmarkHttpItems::TABLE)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(20)
            ->get();
    }
}
