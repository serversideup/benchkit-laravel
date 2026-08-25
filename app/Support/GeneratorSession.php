<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The durable record of an external load generator pairing.
 *
 * One mechanism serves two moments: the start screen surfaces it before a run
 * (pair, handshake, show the measurable ceiling), and the HTTP stage waits on
 * it during one. The generator script holds the token; the browser and the
 * detached run process share this file the same way they share RunState.
 *
 *   generator.json  the pairing record (token, status, handshake, uploads)
 *
 * Statuses: waiting (token minted, nothing connected yet), connected (a
 * generator handshaked), armed (the run's HTTP stage is waiting for load),
 * running (the generator fetched its work), done, error.
 */
class GeneratorSession
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_ARMED = 'armed';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_ERROR = 'error';

    /**
     * A pairing nobody has touched for this long is abandoned. Generator
     * polls refresh last_seen_at, so a session paired before a long hardware
     * stage survives as long as its script keeps polling.
     */
    protected const EXPIRES_AFTER_SECONDS = 3600;

    /** Spam cap: rejections beyond this stop being recorded individually. */
    protected const MAX_REJECTIONS = 20;

    /**
     * Mint a new pairing, replacing any session that is not bound to a live
     * run. A session bound to an active run is returned unchanged — rotating
     * the token mid-run would strand the generator that holds it.
     *
     * @return array<string, mixed>
     */
    public function create(string $targetUrl, string $baseUrl): array
    {
        $current = $this->current();

        if ($current !== null && $this->boundToActiveRun($current)) {
            return $current;
        }

        return $this->write([
            'token' => bin2hex(random_bytes(20)),
            'status' => self::STATUS_WAITING,
            'created_at' => now()->utc()->toIso8601String(),
            'last_seen_at' => now()->utc()->toIso8601String(),
            'target_url' => rtrim($targetUrl, '/'),
            'base_url' => rtrim($baseUrl, '/'),
            'run_id' => null,
            'handshake' => null,
            'work' => null,
            'received' => [],
            'rejections' => [],
        ]);
    }

    /**
     * The current pairing, or null when none exists or the one on disk has
     * been abandoned. Expiry and stranding are both reconciled on read, like
     * RunState's dead-PID check, so every reader self-heals.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $session = $this->read();

        if ($session === null) {
            return null;
        }

        if ($this->isExpired($session) || $this->isStranded($session)) {
            $this->forget();

            return null;
        }

        return $session;
    }

    /**
     * Timing-safe token check. Every generator endpoint 404s on a mismatch,
     * so a wrong token is indistinguishable from no endpoint at all.
     */
    public function matches(?string $token): bool
    {
        $current = $this->current()['token'] ?? null;

        return is_string($token) && is_string($current) && hash_equals($current, $token);
    }

    /**
     * @param  array{oha_version: string|null, cores: int|null, host: string|null, rtt_ms: float|null, source_ip: string|null}  $handshake
     * @return array<string, mixed>
     */
    public function recordHandshake(array $handshake): array
    {
        return $this->merge([
            'status' => $this->status() === self::STATUS_WAITING ? self::STATUS_CONNECTED : $this->status(),
            'handshake' => [...$handshake, 'connected_at' => now()->utc()->toIso8601String()],
            'last_seen_at' => now()->utc()->toIso8601String(),
        ]);
    }

    /**
     * The run's HTTP stage is waiting: record which run, and the work the
     * generator should fetch — rendered from the run's actual settings, which
     * outrank whatever the settings were at pairing time.
     *
     * @return array<string, mixed>
     */
    public function arm(string $runId, string $work): array
    {
        return $this->merge([
            'status' => self::STATUS_ARMED,
            'run_id' => $runId,
            'work' => $work,
            'received' => [],
            'rejections' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function markWorkFetched(): array
    {
        return $this->merge(['status' => self::STATUS_RUNNING, 'last_seen_at' => now()->utc()->toIso8601String()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordReceived(string $route, float $requestsPerSecond, ?string $sourceIp): array
    {
        $received = $this->current()['received'] ?? [];
        $received[$route] = [
            'at' => now()->utc()->toIso8601String(),
            'requests_per_second' => $requestsPerSecond,
            'source_ip' => $sourceIp,
        ];

        return $this->merge(['received' => $received, 'last_seen_at' => now()->utc()->toIso8601String()]);
    }

    /**
     * A rejected upload, kept so the waiting stage can print the reason into
     * the run console — the person who needs it is watching a terminal on
     * another machine.
     *
     * @return array<string, mixed>
     */
    public function recordRejection(string $route, string $reason, ?string $sourceIp): array
    {
        $rejections = $this->current()['rejections'] ?? [];

        if (count($rejections) < self::MAX_REJECTIONS) {
            $rejections[] = [
                'at' => now()->utc()->toIso8601String(),
                'route' => $route,
                'reason' => $reason,
                'source_ip' => $sourceIp,
            ];
        }

        return $this->merge(['rejections' => $rejections]);
    }

    public function touch(): void
    {
        $this->merge(['last_seen_at' => now()->utc()->toIso8601String()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function finish(string $status): array
    {
        return $this->merge(['status' => $status, 'work' => null]);
    }

    public function forget(): void
    {
        File::delete($this->path());
    }

    public function status(): ?string
    {
        return $this->current()['status'] ?? null;
    }

    /**
     * What the browser gets to see, folded into the run-log poll. The
     * generator's IP stays out — it reaches the run console and the meta
     * file, where it is deliberate, not every idle poll response.
     *
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        $session = $this->current();

        // A finished pairing is history, not something the start screen
        // should present as connected — reporting none prompts the UI to
        // mint a fresh one when it next needs a pairing.
        if ($session === null || in_array($session['status'], [self::STATUS_DONE, self::STATUS_ERROR], true)) {
            return null;
        }

        $handshake = $session['handshake'];

        return [
            'token' => $session['token'],
            'status' => $session['status'],
            'target_url' => $session['target_url'],
            'handshake' => $handshake === null ? null : [
                'oha_version' => $handshake['oha_version'] ?? null,
                'cores' => $handshake['cores'] ?? null,
                'host' => $handshake['host'] ?? null,
                'rtt_ms' => $handshake['rtt_ms'] ?? null,
                'connected_at' => $handshake['connected_at'] ?? null,
            ],
            'received' => array_keys($session['received'] ?? []),
        ];
    }

    public function path(): string
    {
        return config('benchmark.run_path').'/generator.json';
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function boundToActiveRun(array $session): bool
    {
        if (($session['run_id'] ?? null) === null) {
            return false;
        }

        $run = (new RunState)->current();

        return ($run['status'] ?? null) === RunState::STATUS_RUNNING && ($run['id'] ?? null) === $session['run_id'];
    }

    /**
     * Armed or already driving load for a run that is no longer the live one.
     * Nothing can arrive on a pairing in that state: every endpoint the
     * generator can still reach refuses a run that has ended, so its script
     * exits, and the record left behind is a machine that has gone.
     *
     * Cancelling is what makes this necessary — the waiting stage is killed
     * where it stands and never gets to retire the pairing itself. Without
     * this the start screen keeps reporting a connected generator, skips the
     * pairing dialog, and the next external run waits out its timeout.
     *
     * @param  array<string, mixed>  $session
     */
    protected function isStranded(array $session): bool
    {
        return in_array($session['status'] ?? null, [self::STATUS_ARMED, self::STATUS_RUNNING], true)
            && ! $this->boundToActiveRun($session);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function isExpired(array $session): bool
    {
        if ($this->boundToActiveRun($session)) {
            return false;
        }

        $lastSeen = strtotime($session['last_seen_at'] ?? '') ?: 0;

        return $lastSeen < time() - self::EXPIRES_AFTER_SECONDS;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(): ?array
    {
        if (! File::exists($this->path())) {
            return null;
        }

        $session = json_decode(File::get($this->path()), true);

        return is_array($session) ? $session : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function merge(array $attributes): array
    {
        return $this->write([...($this->read() ?? []), ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function write(array $session): array
    {
        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $session;
    }
}
