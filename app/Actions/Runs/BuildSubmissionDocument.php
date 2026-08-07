<?php

namespace App\Actions\Runs;

use App\Actions\Specs\PhpSpecs;
use App\Support\HostCost;

/**
 * The public shape of a run: everything the community gallery publishes, and
 * nothing else.
 *
 * This is an allow-list, never a drop-list. A stored snapshot carries the
 * submitter's public IP (in logs), their APP_URL and internal hostname
 * (http.target, laravel.environment.url), the raw YABS ip_info block, and their
 * opcache.preload path. None of that belongs in a public repo, so this copies
 * only fields chosen deliberately — a field is published because someone
 * decided to publish it, not because nobody thought to remove it.
 *
 * The document produced here is what gets compressed into a submission token
 * (see EncodeSubmissionToken) and what the bot writes to docs/data/runs.
 */
class BuildSubmissionDocument
{
    /**
     * php.ini directives BenchKit records but does not publish.
     *
     * opcache.preload is a filesystem path, which would expose the submitter's
     * directory layout and often a project or company name. Whether preloading
     * is on is the part that explains the number, and that ships as
     * opcache.preload_enabled below. Everything else in PhpSpecs::INI_KEYS is
     * published — read from that constant rather than retyped, so a directive
     * added to the specs snapshot can't quietly start or stop being public
     * without someone editing this list.
     *
     * @var array<int, string>
     */
    protected const WITHHELD_INI_KEYS = ['opcache.preload'];

    /**
     * pm is one of three words and max_children is digits (PhpSpecs extracts
     * them with those exact shapes), so anything else means we misread a pool
     * file and the safe answer is to publish nothing.
     *
     * @var array<int, string>
     */
    protected const FPM_MODES = ['static', 'dynamic', 'ondemand'];

    /** The HTTP routes the gallery compares, in display order. */
    protected const ROUTES = ['static', 'json', 'db_read', 'io'];

    /** phpbench emits one row per subject; a Full run is ~80, and this bounds a runaway parse. */
    protected const MAX_SUBJECTS = 100;

    protected const MAX_STATUS_CODES = 20;

    /**
     * @param  array<string, mixed>  $run  A stored run snapshot (storage/app/runs).
     * @return array<string, mixed>
     */
    public function execute(array $run): array
    {
        $environment = $this->arr($run, 'environment');
        $benchmarks = $this->arr($run, 'benchmarks');

        return [
            'schema_version' => $run['schema_version'] ?? 1,
            'id' => $run['id'] ?? null,
            'created_at' => $run['created_at'] ?? null,
            'meta' => [
                'label' => $run['meta']['label'] ?? 'BenchKit run',
                'provider' => $run['meta']['provider'] ?? null,
                // plan_notes is the pre-split legacy field, still present on
                // older snapshots and still honoured everywhere else in the app.
                'plan' => $run['meta']['plan'] ?? $run['meta']['plan_notes'] ?? null,
                'datacenter' => $run['meta']['datacenter'] ?? null,
                // Structured, always monthly, currency as billed. Runs
                // snapshotted before cost was structured still hold free text,
                // so normalize on the way out rather than shipping two shapes
                // into the public gallery.
                'cost' => HostCost::normalize($run['meta']['cost'] ?? null),
            ],
            // Which benchmarks ran and how they were configured — without these
            // a number can't be compared to another number.
            'settings_preset' => $run['settings_preset'] ?? null,
            'stages_completed' => array_values($this->arr($run, 'stages_completed')),
            'environment' => $this->environment($environment),
            'benchmarks' => [
                'http' => $this->http($benchmarks),
                'php' => $this->php($benchmarks),
                'cfspeedtest' => $this->cfspeedtest($benchmarks),
                'geekbench' => $this->geekbench($run, $benchmarks),
                'disk' => $this->disk($benchmarks),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    protected function environment(array $environment): array
    {
        $server = $this->arr($environment, 'server');
        $php = $this->arr($environment, 'php');
        $laravel = $this->arr($environment, 'laravel');

        return $this->present([
            'server' => $this->present([
                'cpu_model' => $server['cpu_model'] ?? null,
                'cpu_cores' => $server['cpu_cores'] ?? null,
                'cpu_frequency' => $server['cpu_frequency'] ?? null,
                'os' => $server['os'] ?? null,
                'ram' => $server['ram'] ?? null,
            ]),
            'php' => $this->present([
                'php_version' => $php['php_version'] ?? null,
                'php_variation' => $php['php_variation'] ?? null,
                'php_server_api' => $this->sapi($php['php_server_api'] ?? null),
                'octane' => $php['octane'] ?? null,
                'op_cache' => $php['op_cache'] ?? null,
                'memory_limit' => $php['memory_limit'] ?? null,
                // The "which knob moved the number" data: JIT, opcache sizing,
                // and worker counts explain more of the spread between two runs
                // than the hardware often does.
                'ini' => $this->ini($php['ini'] ?? null),
                'serving' => $this->serving($php['serving'] ?? null),
            ]),
            'laravel' => $this->present([
                'environment' => $this->present([
                    'laravel_version' => $laravel['environment']['laravel_version'] ?? null,
                ]),
                'drivers' => $laravel['drivers'] ?? null,
            ]),
            'build_version' => $this->buildVersion($environment['build_version'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $benchmarks
     * @return array<string, mixed>|null
     */
    protected function http(array $benchmarks): ?array
    {
        $http = $benchmarks['http'] ?? null;

        if (! is_array($http)) {
            return null;
        }

        $routes = [];

        foreach (self::ROUTES as $key) {
            if (is_array($http['routes'][$key] ?? null)) {
                $routes[$key] = $this->route($http['routes'][$key]);
            }
        }

        return [
            'mode' => $http['mode'] ?? null,
            'duration_seconds' => $http['duration_seconds'] ?? null,
            'connections' => $http['connections'] ?? null,
            'io_ms' => $http['io_ms'] ?? null,
            // Pool size is config, not identity, and without it a reader can't
            // tell a framework result from one bounded by pm.max_children.
            'fpm_max_children' => $http['fpm_max_children'] ?? null,
            'pool_limited' => $http['pool_limited'] ?? null,
            'routes' => $this->object($routes),
        ];
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function route(array $route): array
    {
        return $this->present([
            'path' => $route['path'] ?? null,
            'requests_per_second' => $route['requests_per_second'] ?? null,
            'success_rate' => $route['success_rate'] ?? null,
            'p50_ms' => $route['p50_ms'] ?? null,
            'p95_ms' => $route['p95_ms'] ?? null,
            'p99_ms' => $route['p99_ms'] ?? null,
            'total_requests' => $route['total_requests'] ?? null,
            'status_codes' => $this->statusCodes($route['status_codes'] ?? null),
        ]);
    }

    /**
     * Status codes turn "fast" into "fast and actually served 200s" — a run
     * that 500s under load should be visible, not averaged in silently.
     *
     * @return array<string, int>|null
     */
    protected function statusCodes(mixed $codes): ?array
    {
        if (! is_array($codes)) {
            return null;
        }

        $kept = [];

        foreach ($codes as $code => $count) {
            if (count($kept) >= self::MAX_STATUS_CODES) {
                break;
            }

            if (preg_match('/^\d{3}$/', (string) $code) && (is_int($count) || is_float($count))) {
                $kept[(string) $code] = $count;
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * @param  array<string, mixed>  $benchmarks
     * @return array<string, mixed>|null
     */
    protected function php(array $benchmarks): ?array
    {
        $headline = $benchmarks['php']['headline'] ?? null;

        if (! is_array($headline)) {
            return null;
        }

        return $this->present([
            'headline' => $headline,
            'subjects' => $this->subjects($benchmarks['php']['subjects'] ?? null),
        ]);
    }

    /**
     * phpbench subject rows: our own class and method names plus a mean. Names
     * are matched against an identifier shape so a malformed CSV can't smuggle
     * text through this field.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function subjects(mixed $subjects): ?array
    {
        if (! is_array($subjects)) {
            return null;
        }

        $kept = [];

        foreach ($subjects as $row) {
            if (count($kept) >= self::MAX_SUBJECTS) {
                break;
            }

            $benchmark = $row['benchmark'] ?? null;
            $subject = $row['subject'] ?? null;
            $mean = $row['mean_us'] ?? null;

            if ($this->isIdentifier($benchmark) && $this->isIdentifier($subject) && (is_int($mean) || is_float($mean))) {
                $kept[] = ['benchmark' => $benchmark, 'subject' => $subject, 'mean_us' => $mean];
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * @param  array<string, mixed>  $benchmarks
     * @return array<string, mixed>|null
     */
    protected function cfspeedtest(array $benchmarks): ?array
    {
        $cfspeedtest = $benchmarks['cfspeedtest'] ?? null;

        if (! is_array($cfspeedtest)) {
            return null;
        }

        // ASN and colo stay local. On a datacenter run they name the host, but
        // on someone's home hardware they are their residential ISP and their
        // nearest city — and the speeds are the part the community is
        // comparing anyway.
        return [
            'latency_ms' => $cfspeedtest['latency_ms'] ?? null,
            'download_mbps' => $cfspeedtest['download_mbps'] ?? null,
            'upload_mbps' => $cfspeedtest['upload_mbps'] ?? null,
        ];
    }

    /**
     * Hardware benchmarks live under yabs in the snapshot; flatten to the clean
     * gallery shape (the same mapping runDisplay uses for the app's Hardware
     * panel).
     *
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $benchmarks
     * @return array<string, mixed>|null
     */
    protected function geekbench(array $run, array $benchmarks): ?array
    {
        $geekbench = $benchmarks['yabs']['geekbench'][0] ?? null;

        if (! is_array($geekbench) || empty($geekbench['single']) || empty($geekbench['multi'])) {
            return null;
        }

        return [
            'single' => $geekbench['single'],
            'multi' => $geekbench['multi'],
            'version' => $geekbench['version'] ?? $run['settings']['geekbench_version'] ?? null,
            'url' => $run['extras']['geekbench_url'] ?? $geekbench['url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $benchmarks
     * @return array<int, array<string, mixed>>|null
     */
    protected function disk(array $benchmarks): ?array
    {
        $fio = $benchmarks['yabs']['fio'] ?? null;

        if (! is_array($fio) || $fio === []) {
            return null;
        }

        return array_values(array_map(fn (array $row) => $this->present([
            'bs' => $row['bs'] ?? null,
            'speed_r' => $row['speed_r'] ?? null,
            'speed_w' => $row['speed_w'] ?? null,
            'speed_rw' => $row['speed_rw'] ?? null,
        ]), array_filter($fio, 'is_array')));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ini(mixed $ini): ?array
    {
        if (! is_array($ini)) {
            return null;
        }

        $kept = [];

        foreach (PhpSpecs::INI_KEYS as $key) {
            if (in_array($key, self::WITHHELD_INI_KEYS, true)) {
                continue;
            }

            $value = $ini[$key] ?? null;

            if ($value === null || $value === false) {
                continue;
            }

            $kept[$key] = mb_substr((string) $value, 0, 40);
        }

        $preload = $ini['opcache.preload'] ?? null;
        $kept['opcache.preload_enabled'] = is_string($preload) && $preload !== '';

        return $kept;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function serving(mixed $serving): ?array
    {
        if (! is_array($serving)) {
            return null;
        }

        $trimmed = [];

        if (in_array($serving['fpm_pm'] ?? null, self::FPM_MODES, true)) {
            $trimmed['fpm_pm'] = $serving['fpm_pm'];
        }

        $children = filter_var($serving['fpm_max_children'] ?? null, FILTER_VALIDATE_INT);

        if ($children !== false && $children > 0) {
            $trimmed['fpm_max_children'] = $children;
        }

        return $trimmed === [] ? null : $trimmed;
    }

    /**
     * Which BenchKit built the run, so a change in the app itself is
     * distinguishable from a change in the hardware. A self-built image can be
     * tagged anything, including "ghcr.io/acme-corp/benchkit", so only a plain
     * version-like tag is published — anything else is dropped rather than
     * guessed at.
     */
    protected function buildVersion(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/', $value) ? $value : null;
    }

    protected function sapi(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9+-]{1,30}$/', $value) ? $value : null;
    }

    protected function isIdentifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_]{1,60}$/', $value) === 1;
    }

    /**
     * Drop keys we couldn't detect rather than publishing them as null. A null
     * in a public document reads like a measurement of nothing; an absent key
     * reads like what it is.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected function present(array $value): array
    {
        return array_filter($value, fn (mixed $item) => $item !== null);
    }

    /**
     * json_encode turns an empty PHP array into `[]`, but these fields are
     * objects in the published schema and the gallery reads them by key.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>|\stdClass
     */
    protected function object(array $value): array|\stdClass
    {
        return $value === [] ? new \stdClass : $value;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<mixed>
     */
    protected function arr(array $source, string $key): array
    {
        return is_array($source[$key] ?? null) ? $source[$key] : [];
    }
}
