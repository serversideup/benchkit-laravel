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
     * The list itself lives on PhpSpecs, next to the snapshot it applies to, so
     * the endpoint that serves the runtime and the document that publishes it
     * cannot disagree about what is private. Everything else in
     * PhpSpecs::INI_KEYS is published — read from that constant rather than
     * retyped, so a directive added to the specs snapshot can't quietly start
     * or stop being public without someone editing that list.
     *
     * opcache.preload is withheld because it is a filesystem path; whether
     * preloading is on is the part that explains the number, and that ships as
     * opcache.preload_enabled below.
     *
     * @var array<int, string>
     */
    protected const WITHHELD_INI_KEYS = PhpSpecs::PRIVATE_INI_KEYS;

    /**
     * The runtimes ServingRuntime can identify. Anything outside this list means
     * detection produced something we did not write, and the safe answer is to
     * publish nothing rather than a value the gallery will filter on.
     *
     * @var array<int, string>
     */
    protected const KNOWN_SERVERS = ['php-fpm', 'frankenphp', 'swoole', 'roadrunner', 'mod_php', 'cli-server', 'litespeed'];

    /** Whether the application stays in memory between requests. */
    protected const SERVING_MODES = ['worker', 'process-per-request'];

    /** Server-specific tuning is a handful of directives, not a config dump. */
    protected const MAX_RUNTIME_SETTINGS = 25;

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
                'runtime' => $this->runtime($php['runtime'] ?? null),
            ]),
            // Which process the php block above describes. A run assembled
            // without the HTTP stage reports the CLI's opcache and memory
            // limit, which are not the ones that served anything.
            'php_environment_source' => $this->environmentSource($environment['php_environment_source'] ?? null),
            'laravel' => $this->present([
                'environment' => $this->present([
                    'laravel_version' => $laravel['environment']['laravel_version'] ?? null,
                    // Debug mode turns every request into a stack-trace-ready
                    // one. A run measured with it on is not measuring the
                    // configuration anybody deploys, and the difference is
                    // large enough that publishing the numbers without the flag
                    // beside them is misleading.
                    'debug_mode' => $this->boolean($laravel['environment']['debug_mode'] ?? null),
                    'app_env' => $this->identifier($laravel['environment']['environment'] ?? null, 20),
                ]),
                'drivers' => $laravel['drivers'] ?? null,
            ]),
            // Write numbers cannot be read without knowing whether the database
            // was durably committing them. Collected since schema 3 and, until
            // now, dropped on the way out — so every submission tripped the
            // validator's "does not report its durability settings" warning.
            'database' => $this->database($environment['database'] ?? null),
            'build_version' => $this->buildVersion($environment['build_version'] ?? null),
        ]);
    }

    /**
     * @return 'cli'|'web'|null
     */
    protected function environmentSource(mixed $source): ?string
    {
        return in_array($source, ['cli', 'web'], true) ? $source : null;
    }

    protected function boolean(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * Driver names, versions, and durability settings, each a short identifier
     * or number the probe read back from the database itself. The SQLite branch
     * also reports a filesystem type; the path it was read from never leaves
     * DatabaseSpecs.
     *
     * @return array<string, mixed>|null
     */
    protected function database(mixed $database): ?array
    {
        if (! is_array($database)) {
            return null;
        }

        $durability = [];

        foreach (is_array($database['durability'] ?? null) ? $database['durability'] : [] as $setting => $value) {
            if (is_string($setting) && preg_match('/^[a-z_]+$/', $setting)) {
                $durability[$setting] = $this->identifier($value, 30);
            }
        }

        return $this->present([
            'driver' => $this->identifier($database['driver'] ?? null, 20),
            'version' => $this->identifier($database['version'] ?? null, 30),
            'filesystem' => $this->identifier($database['filesystem'] ?? null, 20),
            'durability' => $durability === [] ? null : $durability,
        ]);
    }

    /**
     * A short, self-describing value — a driver name, a version, a pragma
     * setting. Anything with a space, a slash, or a length is not one of those
     * and is dropped rather than published on a guess.
     */
    protected function identifier(mixed $value, int $max): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z0-9._+-]{1,'.$max.'}$/', $value) === 1 ? $value : null;
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
            // "loopback" covers both the plaintext and the TLS container port,
            // and a handshake plus per-request encryption is a large difference
            // to leave undisclosed between two otherwise identical-looking runs.
            'tls' => $this->boolean($http['tls'] ?? null),
            'duration_seconds' => $http['duration_seconds'] ?? null,
            'connections' => $http['connections'] ?? null,
            'io_ms' => $http['io_ms'] ?? null,
            // The concurrency ceiling is config, not identity, and without it a
            // reader cannot tell a framework result from one bounded by how the
            // server was sized.
            'workers' => $http['workers'] ?? null,
            // Whether the load offered more concurrency than the server can
            // take (a property of the test) and whether the worker count is
            // demonstrably what capped it (a claim needing evidence — see
            // HttpBenchmarkResults::isPoolLimited).
            'oversubscribed' => $this->boolean($http['oversubscribed'] ?? null),
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
            // Observed, against the requested duration_seconds on the parent.
            // A throughput figure is a count divided by a time, and this is the
            // time it was actually divided by.
            'elapsed_seconds' => $route['elapsed_seconds'] ?? null,
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

        $kept = [];

        foreach (['create', 'read', 'update', 'delete'] as $operation) {
            if (is_array($headline[$operation] ?? null)) {
                $kept[$operation] = $this->headlineOperation($headline[$operation]);
            }
        }

        return $this->present([
            'headline' => $kept === [] ? null : $kept,
            'subjects' => $this->subjects($benchmarks['php']['subjects'] ?? null),
        ]);
    }

    /**
     * One CRUD tile. This was the last branch passing a nested array straight
     * through, which made it the one place a field added upstream would have
     * started publishing itself without anyone choosing to publish it.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function headlineOperation(array $operation): array
    {
        return $this->present([
            'milliseconds' => $this->number($operation['milliseconds'] ?? null),
            'records' => $this->number($operation['records'] ?? null),
            // What makes the four tiles safe to draw on one scale: same count,
            // or no shared scale. The gallery checks it rather than trusting
            // the run's own schema version.
            'statements' => $this->number($operation['statements'] ?? null),
            'best_ms' => $this->number($operation['best_ms'] ?? null),
            'worst_ms' => $this->number($operation['worst_ms'] ?? null),
            'rstdev' => $this->number($operation['rstdev'] ?? null),
            'iterations' => $this->number($operation['iterations'] ?? null),
        ]);
    }

    protected function number(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
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
                // The spread travels with the mean here as well as on the
                // headline. A mean on its own cannot be judged, and these rows
                // are the ones a reader opens when a headline looks wrong.
                $kept[] = $this->present([
                    'benchmark' => $benchmark,
                    'subject' => $subject,
                    'mean_us' => $mean,
                    'best_us' => $this->number($row['best_us'] ?? null),
                    'worst_us' => $this->number($row['worst_us'] ?? null),
                    'rstdev' => $this->number($row['rstdev'] ?? null),
                    'iterations' => $this->number($row['iterations'] ?? null),
                    'revolutions' => $this->number($row['revolutions'] ?? null),
                ]);
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
     * How the application was served. The three normalized fields are what make
     * two runs comparable across images; `settings` is whatever that particular
     * server exposes, published as a plain map so a runtime BenchKit has never
     * seen still contributes something readable rather than nothing.
     *
     * Keys and values are both shape-checked. This block is assembled from
     * environment variables and a config file on the submitter's machine, and
     * an operator can put anything in either.
     *
     * @return array<string, mixed>|null
     */
    protected function runtime(mixed $runtime): ?array
    {
        if (! is_array($runtime)) {
            return null;
        }

        $settings = [];

        foreach (is_array($runtime['settings'] ?? null) ? $runtime['settings'] : [] as $key => $value) {
            if (count($settings) >= self::MAX_RUNTIME_SETTINGS) {
                break;
            }

            if (is_string($key) && preg_match('/^[a-z_]+(?:\.[a-z_]+)*$/', $key) && ($clean = $this->identifier($value, 30))) {
                $settings[$key] = $clean;
            }
        }

        $workers = filter_var($runtime['workers'] ?? null, FILTER_VALIDATE_INT);

        return $this->present([
            'server' => in_array($runtime['server'] ?? null, self::KNOWN_SERVERS, true) ? $runtime['server'] : null,
            'mode' => in_array($runtime['mode'] ?? null, self::SERVING_MODES, true) ? $runtime['mode'] : null,
            'workers' => $workers !== false && $workers > 0 ? $workers : null,
            'workers_source' => $this->identifier($runtime['workers_source'] ?? null, 30),
            'front_end' => $this->identifier($runtime['front_end'] ?? null, 30),
            'front_end_version' => $this->identifier($runtime['front_end_version'] ?? null, 20),
            'settings' => $settings === [] ? null : $settings,
        ]);
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
