<?php

namespace App\Actions\Runs;

use App\Actions\Results\AssembleResultsDocument;
use App\Support\HostCost;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateRunSnapshot
{
    public const STAGES = ['yabs', 'cfspeedtest', 'http', 'php'];

    /**
     * Freeze a completed run into a durable snapshot document. Benchmarks
     * not part of this run are nulled out — the results directory keeps
     * stale files from earlier runs, so presence on disk proves nothing.
     *
     * @param array{
     *     stages_completed: array<int, string>,
     *     settings: array<string, mixed>,
     *     provider?: ?string,
     *     logs?: array<string, array<int, string>>
     * } $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload): array
    {
        $document = (new AssembleResultsDocument)->execute();
        $stagesCompleted = array_values(array_intersect(self::STAGES, $payload['stages_completed']));

        $benchmarks = [];

        foreach (self::STAGES as $stage) {
            $benchmarks[$stage] = in_array($stage, $stagesCompleted, true)
                ? $document['benchmarks'][$stage]
                : null;
        }

        $logs = (new ScrubLogs)->execute(
            array_intersect_key($payload['logs'] ?? [], array_flip($stagesCompleted)),
        );

        $snapshot = $this->buildSnapshot($payload, $document['environment'], $benchmarks, $stagesCompleted, $logs);

        $written = Storage::disk('runs')->put("{$snapshot['id']}.json", json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $written) {
            throw new \RuntimeException('Could not write the run snapshot to storage/app/runs — check that the directory exists and is writable by the application user.');
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $environment
     * @param  array<string, ?array>  $benchmarks
     * @param  array<int, string>  $stagesCompleted
     * @param  array<string, array<int, string>>  $logs
     * @return array<string, mixed>
     */
    protected function buildSnapshot(array $payload, array $environment, array $benchmarks, array $stagesCompleted, array $logs): array
    {
        $provider = $payload['provider'] ?? null;
        $createdAt = now()->utc();
        $id = $createdAt->format('Ymd-His').'-'.Str::lower(Str::random(4));

        return [
            'schema_version' => 2,
            'type' => 'benchkit-run',
            'id' => $id,
            'created_at' => $createdAt->toIso8601String(),
            'meta' => [
                'label' => $this->autoLabel($environment, $payload, $stagesCompleted),
                'provider' => $provider,
                'provider_source' => $provider !== null ? ($payload['provider_source'] ?? 'ripe') : null,
                'plan' => $payload['plan'] ?? null,
                'datacenter' => $payload['datacenter'] ?? null,
                // Normalized here so every snapshot on disk holds one shape,
                // including runs started by a client that still remembers a
                // free-text cost from before it was structured.
                'cost' => HostCost::normalize($payload['cost'] ?? null),
            ],
            'settings' => $payload['settings'],
            'settings_preset' => $payload['preset'] ?? null,
            'stages_completed' => $stagesCompleted,
            'environment' => $environment,
            'benchmarks' => $benchmarks,
            'extras' => [
                'geekbench_url' => $benchmarks['yabs']['geekbench'][0]['url'] ?? null,
            ],
            'summary' => $this->summarize($benchmarks, $environment),
            'logs' => $logs,
        ];
    }

    /**
     * The run's created_at is displayed alongside the label everywhere, so
     * the auto-label deliberately excludes the timestamp.
     * Format: "fpm-nginx (Quick|Full|Custom)" — the preset the run used,
     * falling back to the phpbench suite mode for older clients.
     *
     * @param  array<string, mixed>  $environment
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $stagesCompleted
     */
    protected function autoLabel(array $environment, array $payload, array $stagesCompleted): string
    {
        $base = $this->serverLabel($environment) ?? 'Benchmark run';
        $mode = $payload['preset'] ?? null;

        if ($mode === null && in_array('php', $stagesCompleted, true) && isset($payload['settings']['php_mode'])) {
            $mode = $payload['settings']['php_mode'];
        }

        return $mode !== null ? sprintf('%s (%s)', $base, ucfirst($mode)) : $base;
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    protected function serverLabel(array $environment): ?string
    {
        $base = $environment['php']['php_variation'] ?? $environment['php']['php_server_api'] ?? null;

        if ($base === null) {
            return null;
        }

        return ($environment['php']['octane'] ?? false) ? "{$base} + octane" : $base;
    }

    /**
     * Headline metrics precomputed at store time so listing runs never
     * has to re-derive them from full benchmark payloads.
     *
     * @param  array<string, ?array>  $benchmarks
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    protected function summarize(array $benchmarks, array $environment): array
    {
        $heroRoute = collect(['db_read', 'json', 'static'])
            ->map(fn (string $key) => $benchmarks['http']['routes'][$key] ?? null)
            ->first(fn (?array $route) => isset($route['requests_per_second']));

        return [
            'http_rps' => $heroRoute['requests_per_second'] ?? null,
            'http_p95_ms' => $heroRoute['p95_ms'] ?? null,
            'php_create_ms' => $benchmarks['php']['headline']['create']['milliseconds'] ?? null,
            'geekbench_single' => $benchmarks['yabs']['geekbench'][0]['single'] ?? null,
            'geekbench_multi' => $benchmarks['yabs']['geekbench'][0]['multi'] ?? null,
            'download_mbps' => $benchmarks['cfspeedtest']['download_mbps'] ?? null,
            'server_label' => $this->serverLabel($environment),
        ];
    }
}
