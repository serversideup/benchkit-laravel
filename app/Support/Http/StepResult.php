<?php

namespace App\Support\Http;

/**
 * One oha result, reduced to the facts the sweep and the curve need.
 *
 * Reducing here rather than passing the raw document around is what lets the
 * two drivers stay identical: the generator uploads oha's JSON and the local
 * command captures oha's stdout, and both arrive at the same object before
 * anything decides anything.
 */
class StepResult
{
    /**
     * oha counts the requests still in flight when the window closes as
     * errors, and there is always one per connection — c=20 ends with twenty.
     * It is how a timed run stops, not a failure, and treating it as one made
     * every level of every route look broken.
     */
    protected const BENIGN_ERRORS = ['aborted due to deadline'];

    /**
     * @param  array<string|int, int>  $statusCodes
     * @param  array<string, int>  $errors
     */
    public function __construct(
        public readonly float $requestsPerSecond,
        public readonly float $averageSeconds,
        public readonly float $fastestSeconds,
        public readonly float $successRate,
        public readonly float $elapsedSeconds,
        public readonly int $totalRequests,
        public readonly ?float $p50Ms,
        public readonly ?float $p90Ms,
        public readonly ?float $p95Ms,
        public readonly ?float $p99Ms,
        public readonly array $statusCodes,
        public readonly array $errors,
        public readonly bool $usable = true,
        public readonly ?string $failure = null,
    ) {}

    /**
     * @param  array<string, mixed>  $oha
     */
    public static function fromOha(array $oha): self
    {
        $summary = $oha['summary'] ?? [];
        $percentiles = $oha['latencyPercentiles'] ?? [];
        $statusCodes = $oha['statusCodeDistribution'] ?? [];

        return new self(
            requestsPerSecond: round((float) ($summary['requestsPerSec'] ?? 0), 1),
            averageSeconds: (float) ($summary['average'] ?? 0),
            fastestSeconds: (float) ($summary['fastest'] ?? 0),
            successRate: (float) ($summary['successRate'] ?? 0),
            elapsedSeconds: round((float) ($summary['total'] ?? 0), 2),
            totalRequests: (int) array_sum($statusCodes),
            p50Ms: self::milliseconds($percentiles['p50'] ?? null),
            p90Ms: self::milliseconds($percentiles['p90'] ?? null),
            p95Ms: self::milliseconds($percentiles['p95'] ?? null),
            p99Ms: self::milliseconds($percentiles['p99'] ?? null),
            statusCodes: $statusCodes,
            errors: $oha['errorDistribution'] ?? [],
        );
    }

    /**
     * A step that was acknowledged without producing numbers — the warmup.
     */
    public static function skipped(): self
    {
        return new self(0, 0, 0, 0, 0, 0, null, null, null, null, [], [], usable: false);
    }

    /**
     * A step that could not be run, or whose output could not be read.
     */
    public static function failed(string $reason): self
    {
        return new self(0, 0, 0, 0, 0, 0, null, null, null, null, [], [], usable: false, failure: $reason);
    }

    /**
     * Whether every request this step made was answered with a 2xx.
     *
     * oha's own successRate is transport-level: a route answering 503 to
     * every request reports a perfect success rate, and at a *higher* rate
     * than a working one, because an error is cheap to produce. Checking the
     * status distribution is what turns that from a flattering number into a
     * stopped sweep.
     */
    public function isClean(): bool
    {
        if (! $this->usable || $this->successRate < 0.99 || $this->realErrors() !== []) {
            return false;
        }

        foreach ($this->statusCodes as $code => $count) {
            if ((int) $code < 200 || (int) $code >= 300) {
                return false;
            }
        }

        return $this->totalRequests > 0;
    }

    /**
     * How much of the offered concurrency the generator actually kept in
     * flight, as a ratio around 1.0.
     *
     * In a closed loop each connection holds exactly one request at a time, so
     * Little's law is an identity rather than a model: connections = rate ×
     * response time. When that identity holds, the generator was busy and the
     * server was the thing being measured. When it falls short, connections
     * sat idle — client CPU starvation, connection churn, a file-descriptor
     * ceiling — and the step describes the generator instead.
     *
     * This is deliberately computed from the mean rather than p50: the mean is
     * the R in Little's law, and a skewed distribution makes p50 a different
     * quantity that does not satisfy the identity.
     */
    public function connectionEfficiency(int $connections): ?float
    {
        if ($connections < 1 || $this->requestsPerSecond <= 0 || $this->averageSeconds <= 0) {
            return null;
        }

        return round(($this->averageSeconds * $this->requestsPerSecond) / $connections, 3);
    }

    /**
     * Errors that describe something going wrong, as opposed to the window
     * ending. @see self::BENIGN_ERRORS
     *
     * @return array<string, int>
     */
    public function realErrors(): array
    {
        return array_diff_key($this->errors, array_flip(self::BENIGN_ERRORS));
    }

    protected static function milliseconds(mixed $seconds): ?float
    {
        return is_numeric($seconds) ? round((float) $seconds * 1000, 2) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requests_per_second' => $this->requestsPerSecond,
            'average_seconds' => $this->averageSeconds,
            'fastest_seconds' => $this->fastestSeconds,
            'success_rate' => $this->successRate,
            'elapsed_seconds' => $this->elapsedSeconds,
            'total_requests' => $this->totalRequests,
            'p50_ms' => $this->p50Ms,
            'p90_ms' => $this->p90Ms,
            'p95_ms' => $this->p95Ms,
            'p99_ms' => $this->p99Ms,
            'status_codes' => $this->statusCodes,
            'errors' => $this->errors,
            'usable' => $this->usable,
            'failure' => $this->failure,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        return new self(
            requestsPerSecond: (float) $state['requests_per_second'],
            averageSeconds: (float) $state['average_seconds'],
            fastestSeconds: (float) $state['fastest_seconds'],
            successRate: (float) $state['success_rate'],
            elapsedSeconds: (float) $state['elapsed_seconds'],
            totalRequests: (int) $state['total_requests'],
            p50Ms: $state['p50_ms'],
            p90Ms: $state['p90_ms'],
            p95Ms: $state['p95_ms'],
            p99Ms: $state['p99_ms'],
            statusCodes: $state['status_codes'],
            errors: $state['errors'],
            usable: (bool) $state['usable'],
            failure: $state['failure'],
        );
    }
}
