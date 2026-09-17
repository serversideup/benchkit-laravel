<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * What the machine driving the load told us about itself.
 *
 * One shape, built once and projected three ways, because the four hand-copied
 * field lists this replaced carried three different sets of keys — so a field
 * added to the handshake reached the meta file and stopped, with nothing to
 * say it had not arrived.
 *
 * These are measurement conditions, not decoration: they travel with the
 * numbers they condition.
 */
class GeneratorHandshake
{
    public const MODE_SELF = 'self';

    public const MODE_EXTERNAL = 'external';

    /** Free-text fields are sanitized rather than rejected; an odd hostname should still pair. */
    protected const MAX_HOST = 60;

    protected const MAX_RTT_MS = 60_000;

    protected const MAX_CORES = 4096;

    protected const MAX_FD_LIMIT = 1_048_576;

    public function __construct(
        public readonly string $mode = self::MODE_SELF,
        public readonly ?string $ohaVersion = null,
        public readonly ?int $cores = null,
        public readonly ?string $host = null,
        public readonly ?float $rttMs = null,
        public readonly ?float $transportRttMs = null,
        public readonly ?float $rttWorstMs = null,
        public readonly ?int $fdLimit = null,
        public readonly ?string $targetIp = null,
        public readonly ?string $sourceIp = null,
    ) {}

    /** A run that drove its own load: loopback transport really is nothing. */
    public static function self(): self
    {
        return new self(mode: self::MODE_SELF, transportRttMs: 0.0);
    }

    public static function fromRequest(Request $request): self
    {
        $version = $request->input('oha_version');
        $host = $request->input('host');
        $rtt = self::milliseconds($request->input('rtt_ms'));
        $transport = self::milliseconds($request->input('transport_rtt_ms'));

        return new self(
            mode: self::MODE_EXTERNAL,
            ohaVersion: is_string($version) && preg_match('/^[0-9A-Za-z._+-]{1,20}$/', $version) ? $version : null,
            cores: self::bounded($request->input('cores'), self::MAX_CORES),
            host: is_string($host) ? mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $host), 0, self::MAX_HOST) : null,
            rttMs: $rtt,
            // A transport round trip longer than the request that rode on it is
            // jitter in one of the two samples. Unclamped it yields a negative
            // service time, which reads as a server too fast to measure.
            transportRttMs: $transport !== null && $rtt !== null ? min($transport, $rtt) : $transport,
            rttWorstMs: self::milliseconds($request->input('rtt_worst_ms')),
            // One connection is one open file. A generator that cannot hold the
            // concurrency the sweep asks for does not fail slowly: the
            // connections that cannot open are counted as replies, so the
            // throughput jumps by an order of magnitude at exactly the level
            // where the measurement stopped being real.
            fdLimit: self::bounded($request->input('fd_limit'), self::MAX_FD_LIMIT),
            // Where the target's name resolved to from the generator's side, so
            // every window is driven against the address rather than the name.
            targetIp: filter_var($request->input('target_ip'), FILTER_VALIDATE_IP) ?: null,
            sourceIp: $request->ip(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    public static function fromArray(?array $state): self
    {
        if ($state === null) {
            return self::self();
        }

        return new self(
            mode: ($state['mode'] ?? self::MODE_SELF) === self::MODE_EXTERNAL ? self::MODE_EXTERNAL : self::MODE_SELF,
            ohaVersion: $state['oha_version'] ?? null,
            cores: isset($state['cores']) ? (int) $state['cores'] : null,
            host: $state['host'] ?? null,
            rttMs: isset($state['rtt_ms']) ? (float) $state['rtt_ms'] : null,
            transportRttMs: isset($state['transport_rtt_ms']) ? (float) $state['transport_rtt_ms'] : null,
            rttWorstMs: isset($state['rtt_worst_ms']) ? (float) $state['rtt_worst_ms'] : null,
            fdLimit: isset($state['fd_limit']) ? (int) $state['fd_limit'] : null,
            targetIp: $state['target_ip'] ?? null,
            sourceIp: $state['source_ip'] ?? null,
        );
    }

    /**
     * The durable pairing record.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'oha_version' => $this->ohaVersion,
            'cores' => $this->cores,
            'host' => $this->host,
            'rtt_ms' => $this->rttMs,
            'transport_rtt_ms' => $this->transportRttMs,
            'rtt_worst_ms' => $this->rttWorstMs,
            'fd_limit' => $this->fdLimit,
            'target_ip' => $this->targetIp,
            'source_ip' => $this->sourceIp,
        ];
    }

    /**
     * What travels with the numbers, into the run's meta file.
     *
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        return $this->toArray();
    }

    /**
     * What the browser may see. An allow-list rather than the record minus a
     * field, so withholding the generator's address stays a decision.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'oha_version' => $this->ohaVersion,
            'cores' => $this->cores,
            'host' => $this->host,
            'rtt_ms' => $this->rttMs,
            'transport_rtt_ms' => $this->transportRttMs,
        ];
    }

    /** How much of a round trip is jitter rather than distance. */
    public function pathJitterMs(): ?float
    {
        return $this->rttMs === null || $this->rttWorstMs === null
            ? null
            : round(max(0.0, $this->rttWorstMs - $this->rttMs), 2);
    }

    protected static function milliseconds(mixed $value): ?float
    {
        $number = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $number !== false && $number >= 0 && $number <= self::MAX_RTT_MS ? round($number, 2) : null;
    }

    protected static function bounded(mixed $value, int $max): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);

        return $number !== false && $number > 0 && $number <= $max ? $number : null;
    }
}
