<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Specs\PhpSpecs;
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

    /**
     * What PHP looks like *here*, in the process that answers requests.
     *
     * Everything else about a run is assembled by `php artisan benchmark:run`,
     * which is the CLI SAPI: it reads opcache.enable_cli, the CLI memory limit,
     * and reports php_sapi_name() as "cli". None of that describes the FPM or
     * FrankenPHP worker that served the load test, so a results page showing
     * OPcache and JIT next to a throughput figure was describing the wrong
     * process. The HTTP stage already has a target URL it has proved reachable,
     * so it asks that URL instead.
     *
     * This is also the only place the front-end web server is visible:
     * php_sapi_name() is "fpm-fcgi" for nginx and Apache alike, and the
     * SERVER_SOFTWARE that tells them apart exists only on a real request.
     *
     * The payload is the same curated snapshot the landing page already renders
     * and the run document already publishes, minus PhpSpecs::PRIVATE_INI_KEYS.
     */
    public function environment(): JsonResponse
    {
        return response()->json((new PhpSpecs)->publicSnapshot());
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

    /**
     * The table is seeded once by the HTTP stage before the load test starts
     * (BenchmarkStages::httpStage), never from here.
     *
     * This used to create and seed it on a failed query, which is fine for a
     * single request and wrong for the thing this route exists to do: under
     * load a missing table means every request in flight races to run the same
     * CREATE TABLE and 50 inserts, inside the window being measured. Answering
     * 503 instead puts the failure in the status-code distribution, where it
     * reads as "this run is invalid" rather than as an unexplained latency
     * spike in an otherwise publishable number.
     */
    public function dbRead(): JsonResponse
    {
        try {
            $items = $this->queryItems();
        } catch (QueryException) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'The benchmark table is missing. It is prepared when the HTTP stage starts.',
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'items' => $items,
        ]);
    }

    /**
     * Models a request dominated by one outbound dependency call by sleeping a
     * caller-supplied delay. Under FPM the sleep holds a worker, so this route
     * is bounded by pm.max_children as well as by the framework — see the
     * pool_limited flag on the results. Clamped to the 0-1000ms band the
     * settings enforce, so a stray query string can't hang a worker.
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
