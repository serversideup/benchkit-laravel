<?php

namespace App\Http\Controllers\Benchmarks;

use App\Actions\Results\HttpBenchmarkResults;
use App\Http\Controllers\Controller;
use App\Support\GeneratorScript;
use App\Support\GeneratorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The token-addressed endpoints an external load generator talks to. They
 * live on the bench routes (no session, no CSRF — the generator has neither)
 * and 404 whenever there is no pairing or the token does not match, so a
 * wrong token is indistinguishable from no endpoint at all.
 *
 * Error responses are plain text: the reader is a shell script that prints
 * the body straight to the terminal of whoever ran it.
 */
class GeneratorController extends Controller
{
    /** An oha JSON file for a 60s standard run is ~2 KB; this is headroom, not a quota. */
    protected const MAX_UPLOAD_BYTES = 1_048_576;

    public function __construct(protected GeneratorSession $session) {}

    public function script(string $token): Response
    {
        $session = $this->authorized($token);

        // Rendered fresh per request so a re-download always carries the
        // current pairing, and streamed as a shell script for `curl | sh`.
        return response(
            (new GeneratorScript)->bootstrap($session),
            200,
            ['Content-Type' => 'text/x-shellscript; charset=utf-8'],
        );
    }

    public function handshake(Request $request, string $token): JsonResponse|Response
    {
        $session = $this->authorized($token);

        // Once the run is waiting on a specific generator, a second handshake
        // is another machine — first generator wins.
        if (in_array($session['status'], [GeneratorSession::STATUS_ARMED, GeneratorSession::STATUS_RUNNING], true)) {
            return response("Another generator is already driving this run.\n", 409, ['Content-Type' => 'text/plain']);
        }

        $this->session->recordHandshake($this->describeGenerator($request));

        return response()->json(['status' => 'connected', 'poll_seconds' => 2]);
    }

    /**
     * The generator's 2-second poll. Answers by status code so the script
     * needs no JSON parsing: 204 keep waiting, 200 here is your work, 410
     * this pairing is over, 404 there is no such pairing any more — the
     * script stops on either of the last two. Deliberately a plain poll
     * rather than a long poll — a held-open connection would occupy a PHP
     * worker, and no polls happen during measured windows anyway.
     */
    public function work(string $token): Response
    {
        $session = $this->authorized($token);

        if (in_array($session['status'], [GeneratorSession::STATUS_DONE, GeneratorSession::STATUS_ERROR], true)) {
            return response("This run is finished.\n", 410, ['Content-Type' => 'text/plain']);
        }

        // Armed means there is a batch waiting that has not been collected.
        // Running means one has, and the generator is either working through
        // it or waiting for the next — either way there is nothing new to
        // hand over, and handing back the same batch would make it re-run
        // every window it has already uploaded.
        if ($session['status'] !== GeneratorSession::STATUS_ARMED) {
            $this->session->touch();

            return response()->noContent();
        }

        $this->session->markWorkFetched();

        return response($session['work'] ?? '', 200, ['Content-Type' => 'text/x-shellscript; charset=utf-8']);
    }

    /**
     * A window the generator could not measure.
     *
     * Without this a failed window is indistinguishable from a slow one: the
     * generator moves on, the server keeps waiting for a result that is never
     * coming, and the whole run sits there until its no-progress timeout
     * expires. Saying so costs one request and lets the rest of the run
     * finish with an honest gap in the curve.
     */
    public function failed(Request $request, string $token, string $slot): Response
    {
        $session = $this->authorized($token);

        if (! in_array($session['status'], [GeneratorSession::STATUS_ARMED, GeneratorSession::STATUS_RUNNING], true)) {
            abort(404);
        }

        if ((new HttpBenchmarkResults)->pathForSlot($slot) === null) {
            abort(404);
        }

        // Whatever the generator's own tooling said, capped hard: it is a
        // diagnostic from another machine, not something to trust with length.
        $reason = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $request->getContent()), 0, 200);

        $this->session->recordFailed($slot, $request->ip(), trim($reason) ?: null);

        return response("Recorded.\n", 202, ['Content-Type' => 'text/plain']);
    }

    public function upload(Request $request, string $token, string $slot): JsonResponse|Response
    {
        $session = $this->authorized($token);
        $results = new HttpBenchmarkResults;
        $path = $results->pathForSlot($slot);

        // A pairing only reads as armed or running while its run is the live
        // one — GeneratorSession retires it otherwise — so this is also what
        // refuses an upload for a run that has since been cancelled.
        if (! in_array($session['status'], [GeneratorSession::STATUS_ARMED, GeneratorSession::STATUS_RUNNING], true)) {
            abort(404);
        }

        if ($path === null) {
            abort(404);
        }

        $body = $request->getContent();

        if (strlen($body) > self::MAX_UPLOAD_BYTES) {
            return $this->reject($slot, $request->ip(), 'the upload is larger than any oha result file', 413);
        }

        if (($reason = $this->malformed($body)) !== null) {
            return $this->reject($slot, $request->ip(), $reason, 422);
        }

        // First writer wins, atomically: exclusive create fails if the window
        // already landed, so a second generator cannot overwrite a result.
        $handle = @fopen($path, 'x');

        if ($handle === false) {
            return $this->reject($slot, $request->ip(), 'this measurement already has a result — first upload wins', 409);
        }

        fwrite($handle, $body);
        fclose($handle);

        $data = json_decode($body, true);
        $requestsPerSecond = round((float) $data['summary']['requestsPerSec'], 1);

        $this->session->recordReceived($slot, $requestsPerSecond, $request->ip());

        return response()->json(['status' => 'accepted', 'requests_per_second' => $requestsPerSecond], 201);
    }

    /**
     * Shape checks before anything touches the results directory. The same
     * rules the parser applies later, applied here so a bad upload is
     * refused with a reason instead of silently producing an empty stage.
     */
    protected function malformed(string $body): ?string
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            return 'the body is not the JSON oha writes with --output-format json';
        }

        $requestsPerSecond = $data['summary']['requestsPerSec'] ?? null;

        if (! is_numeric($requestsPerSecond) || $requestsPerSecond <= 0) {
            return 'summary.requestsPerSec is missing — is this the measured run, not the warmup?';
        }

        // The same rule HttpBenchmarkResults::hasMeasuredTraffic() applies:
        // zero bytes transferred means the application was never reached.
        $totalData = $data['summary']['totalData'] ?? null;

        if (! is_numeric($totalData) || $totalData <= 0) {
            return 'the run transferred zero bytes — the target answered without ever reaching the application';
        }

        $successes = collect($data['statusCodeDistribution'] ?? [])
            ->filter(fn ($count, $code) => (int) $code >= 200 && (int) $code < 300)
            ->sum();

        if ($successes < 1) {
            return 'no request returned a 2xx — is the target URL pointing at BenchKit?';
        }

        return null;
    }

    protected function reject(string $route, ?string $ip, string $reason, int $status): Response
    {
        $this->session->recordRejection($route, $reason, $ip);

        return response($reason."\n", $status, ['Content-Type' => 'text/plain']);
    }

    /**
     * Handshake fields are informational, so they are sanitized rather than
     * rejected — a generator with an odd hostname should still pair.
     *
     * @return array{oha_version: string|null, cores: int|null, host: string|null, rtt_ms: float|null, rtt_worst_ms: float|null, fd_limit: int|null, target_ip: string|null, source_ip: string|null}
     */
    protected function describeGenerator(Request $request): array
    {
        $version = $request->input('oha_version');
        $host = $request->input('host');
        $cores = filter_var($request->input('cores'), FILTER_VALIDATE_INT);
        $rtt = filter_var($request->input('rtt_ms'), FILTER_VALIDATE_FLOAT);
        $descriptors = filter_var($request->input('fd_limit'), FILTER_VALIDATE_INT);
        $targetIp = filter_var($request->input('target_ip'), FILTER_VALIDATE_IP);
        $worstRtt = filter_var($request->input('rtt_worst_ms'), FILTER_VALIDATE_FLOAT);

        return [
            'oha_version' => is_string($version) && preg_match('/^[0-9A-Za-z._+-]{1,20}$/', $version) ? $version : null,
            'cores' => $cores !== false && $cores > 0 && $cores <= 4096 ? $cores : null,
            'host' => is_string($host) ? mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $host), 0, 60) : null,
            'rtt_ms' => $rtt !== false && $rtt >= 0 && $rtt <= 60_000 ? round($rtt, 2) : null,
            // One connection is one open file. A generator that cannot hold
            // the concurrency the sweep asks for does not fail slowly — the
            // connections that cannot open are counted as replies, which
            // produced a static route reporting 17,201 req/s where the level
            // below it managed 398.
            'fd_limit' => $descriptors !== false && $descriptors > 0 && $descriptors <= 1_048_576 ? $descriptors : null,
            // Where the target's name resolved to from the generator's side.
            // Every window is driven against this rather than the name, so a
            // run cannot fail on a resolver that has had enough.
            'target_ip' => $targetIp !== false ? $targetIp : null,
            // The slowest of the same five samples rtt_ms is the fastest of.
            // Every percentile the run publishes inherits the difference.
            'rtt_worst_ms' => $worstRtt !== false && $worstRtt >= 0 && $worstRtt <= 60_000 ? round($worstRtt, 2) : null,
            'source_ip' => $request->ip(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function authorized(string $token): array
    {
        abort_unless($this->session->matches($token), 404);

        return $this->session->current();
    }
}
