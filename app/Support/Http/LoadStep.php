<?php

namespace App\Support\Http;

/**
 * One oha invocation: the smallest unit of work either driver can be handed.
 *
 * A step is addressed by `index`, which is global across the whole stage
 * rather than per route. The external generator posts its result back to that
 * index, and the server refuses anything that is not the step it is currently
 * waiting for — which is what makes a retried upload cost one round trip
 * instead of corrupting the ramp.
 */
class LoadStep
{
    public const PHASE_WARMUP = 'warmup';

    public const PHASE_SWEEP = 'sweep';

    public const PHASE_LATENCY = 'latency';

    public function __construct(
        public readonly int $index,
        public readonly string $route,
        public readonly string $phase,
        public readonly int $connections,
        public readonly int $durationSeconds,
        public readonly string $url,
        public readonly ?int $qps = null,
        public readonly int $settleSeconds = 0,
    ) {}

    /**
     * Whether this step's output is worth reading back.
     *
     * The warmup exists to leave the worker pool, the connection set, and
     * OPcache warm; its numbers describe a cold server and are never parsed.
     * Saying so here keeps the drivers from having to know which phases mean
     * something — the generator sends `{}` for an uncaptured step, and the
     * state machine advances on the acknowledgement alone.
     */
    public function isMeasured(): bool
    {
        return $this->phase !== self::PHASE_WARMUP;
    }

    /**
     * Whether the raw oha JSON for this step is kept on disk.
     *
     * Ramp rungs are reduced to a curve point and discarded: thirty-odd files
     * of three-second throwaway data are not worth keeping, and nothing may
     * cite a rung as a measurement. The two published passes are kept whole.
     */
    public function isPublished(): bool
    {
        return in_array($this->phase, [self::PHASE_SWEEP, self::PHASE_LATENCY], true);
    }

    /**
     * A short, aligned description for the live console.
     */
    public function label(): string
    {
        $route = str_pad(str_replace('_', '-', $this->route), 9);

        return match ($this->phase) {
            self::PHASE_WARMUP => sprintf('%s %-8s warming up', $route, $this->phase),
            self::PHASE_LATENCY => sprintf('%s %-8s %s req/s', $route, $this->phase, number_format((float) $this->qps)),
            default => sprintf('%s %-8s c=%d', $route, $this->phase, $this->connections),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'route' => $this->route,
            'phase' => $this->phase,
            'connections' => $this->connections,
            'duration_seconds' => $this->durationSeconds,
            'url' => $this->url,
            'qps' => $this->qps,
            'settle_seconds' => $this->settleSeconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        return new self(
            index: (int) $state['index'],
            route: $state['route'],
            phase: $state['phase'],
            connections: (int) $state['connections'],
            durationSeconds: (int) $state['duration_seconds'],
            url: $state['url'],
            qps: $state['qps'] === null ? null : (int) $state['qps'],
            settleSeconds: (int) ($state['settle_seconds'] ?? 0),
        );
    }
}
