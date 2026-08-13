<?php

namespace App\Actions\Specs;

/**
 * How this application is being served: which server, whether it keeps workers
 * alive between requests, and how many requests it can have in flight at once.
 *
 * This replaces a block that could only describe PHP-FPM — two keys named
 * fpm_pm and fpm_max_children, with a UI that hardcoded both. A FrankenPHP
 * thread count, an Octane worker count, and an FPM pool size are the same fact
 * about a host (how much concurrency PHP will accept) under three names, and a
 * gallery that can only read one of them cannot compare the images it exists to
 * compare.
 *
 * So the shape is three normalized fields plus a free-form map:
 *
 *   server / mode / workers   comparable across every runtime
 *   workers_source            what that number is called here, so 20 FPM
 *                             children are never silently equated with 8
 *                             FrankenPHP threads
 *   settings                  whatever this particular server exposes, as
 *                             label => value, rendered generically
 *
 * Every field is null when it cannot be established. A host BenchKit has never
 * seen reports the SAPI it found and nothing else, which is the honest answer
 * and displays as a shorter list rather than as a wrong one.
 *
 * Detection needs the serving process to be asked, not the CLI one — see the
 * /bench/env endpoint. From the command line php_sapi_name() is "cli" and
 * SERVER_SOFTWARE does not exist, so `server` and `front_end` come back null.
 */
class ServingRuntime
{
    /**
     * PHP-FPM pool directives worth recording, mapped to the serversideup image
     * environment variables that set them. Env wins over the pool file: an
     * image builds the file from these, and a value set here is the one the
     * operator chose.
     *
     * @var array<string, string>
     */
    protected const FPM_SETTINGS = [
        'pm' => 'PHP_FPM_PM_CONTROL',
        'pm.max_children' => 'PHP_FPM_PM_MAX_CHILDREN',
        'pm.start_servers' => 'PHP_FPM_PM_START_SERVERS',
        'pm.min_spare_servers' => 'PHP_FPM_PM_MIN_SPARE_SERVERS',
        'pm.max_spare_servers' => 'PHP_FPM_PM_MAX_SPARE_SERVERS',
        'pm.max_requests' => 'PHP_FPM_PM_MAX_REQUESTS',
        'pm.process_idle_timeout' => 'PHP_FPM_PM_PROCESS_IDLE_TIMEOUT',
    ];

    /**
     * Pool file layouts, in the order they are tried: the serversideup images,
     * plain php:fpm, RHEL, then Debian's per-version directories (Forge, Ploi).
     *
     * @var array<int, string>
     */
    protected const POOL_PATHS = [
        '/usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf',
        '/usr/local/etc/php-fpm.d/www.conf',
        '/etc/php-fpm.d/www.conf',
    ];

    /**
     * Front-end servers we can name. Anything else passes through as the token
     * it reported, which is more useful than calling it "unknown".
     *
     * @var array<string, string>
     */
    protected const FRONT_ENDS = [
        'nginx' => 'nginx',
        'apache' => 'apache',
        'caddy' => 'caddy',
        'frankenphp' => 'frankenphp',
        'litespeed' => 'litespeed',
        'openlitespeed' => 'litespeed',
        'lighttpd' => 'lighttpd',
    ];

    /**
     * A directive value: a word, a number, or something like "10s". Anything
     * longer or stranger means the file was not what we assumed, and publishing
     * nothing beats publishing a misread line from a config file.
     */
    protected const VALUE_PATTERN = '/^[A-Za-z0-9._+-]{1,30}$/';

    /**
     * @return array{
     *     server: string|null,
     *     mode: string|null,
     *     workers: int|null,
     *     workers_source: string|null,
     *     front_end: string|null,
     *     front_end_version: string|null,
     *     settings: array<string, string>
     * }
     */
    public function execute(): array
    {
        $server = $this->server();
        [$settings, $workers, $workersSource] = $this->settingsFor($server);
        [$frontEnd, $frontEndVersion] = $this->frontEnd();

        return [
            'server' => $server,
            'mode' => $this->mode($server),
            'workers' => $workers,
            'workers_source' => $workers === null ? null : $workersSource,
            'front_end' => $frontEnd,
            'front_end_version' => $frontEndVersion,
            'settings' => $settings,
        ];
    }

    /**
     * The SAPI names the runtime directly in every case that matters. Octane
     * running under FrankenPHP still reports the frankenphp SAPI; Swoole and
     * RoadRunner run through the CLI SAPI, so they are identified by the
     * extension and the environment Octane sets instead.
     *
     * The pool-file fallback is what keeps this working when the question is
     * asked from the command line, which the run itself has to do when the HTTP
     * stage is off or its environment endpoint could not be reached. A readable
     * FPM pool is good evidence that this machine serves through FPM even when
     * the process doing the asking is the CLI — and `php_environment_source` on
     * the document already tells a reader which process answered.
     */
    protected function server(): ?string
    {
        return match (php_sapi_name()) {
            'fpm-fcgi' => 'php-fpm',
            'frankenphp' => 'frankenphp',
            'apache2handler' => 'mod_php',
            'cli-server' => 'cli-server',
            'litespeed' => 'litespeed',
            default => $this->octaneServer() ?? ($this->hasPoolFile() ? 'php-fpm' : null),
        };
    }

    protected function hasPoolFile(): bool
    {
        foreach ($this->poolFiles() as $pool) {
            if (is_readable($pool)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Swoole and RoadRunner both serve from the CLI SAPI, so the only signals
     * are the loaded extension and Octane's own configuration.
     */
    protected function octaneServer(): ?string
    {
        if (! $this->isWorkerMode()) {
            return null;
        }

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            return 'swoole';
        }

        $configured = getenv('OCTANE_SERVER') ?: config('octane.server');

        return in_array($configured, ['swoole', 'roadrunner', 'frankenphp'], true) ? $configured : null;
    }

    /**
     * Whether the application stays in memory between requests. This is the
     * single most consequential fact about a runtime — it decides whether a
     * request pays for bootstrapping Laravel — and it was previously carried
     * only as a boolean named after the package that provides it.
     */
    protected function mode(?string $server): ?string
    {
        if ($this->isWorkerMode()) {
            return 'worker';
        }

        return $server === null ? null : 'process-per-request';
    }

    /**
     * Octane exports LARAVEL_OCTANE into the worker environment at runtime,
     * after config may already have been cached during an image build, so it is
     * read directly rather than through config. FrankenPHP's own worker mode
     * sets its own marker and can be used without Octane at all.
     */
    protected function isWorkerMode(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE'])
            || getenv('LARAVEL_OCTANE') !== false
            || isset($_SERVER['FRANKENPHP_WORKER'])
            || getenv('FRANKENPHP_WORKER') !== false;
    }

    /**
     * @return array{0: array<string, string>, 1: int|null, 2: string|null}
     */
    protected function settingsFor(?string $server): array
    {
        return match ($server) {
            'php-fpm' => $this->fpm(),
            'frankenphp' => $this->frankenphp(),
            'swoole', 'roadrunner' => $this->octane(),
            default => [[], null, null],
        };
    }

    /**
     * @return array{0: array<string, string>, 1: int|null, 2: string|null}
     */
    protected function fpm(): array
    {
        $settings = [];

        foreach (self::FPM_SETTINGS as $directive => $variable) {
            if ($value = $this->clean(getenv($variable) ?: null)) {
                $settings[$directive] = $value;
            }
        }

        foreach ($this->poolFiles() as $pool) {
            if (count($settings) === count(self::FPM_SETTINGS)) {
                break;
            }

            if (! is_readable($pool)) {
                continue;
            }

            $contents = (string) file_get_contents($pool);

            foreach (self::FPM_SETTINGS as $directive => $variable) {
                if (isset($settings[$directive])) {
                    continue;
                }

                // Anchored to the start of a line so a commented-out directive
                // (";pm.max_children = 5") is never read as a live one.
                if (preg_match('/^\s*'.preg_quote($directive, '/').'\s*=\s*(\S+)/m', $contents, $matches)) {
                    if ($value = $this->clean($matches[1])) {
                        $settings[$directive] = $value;
                    }
                }
            }
        }

        return [$settings, $this->integer($settings['pm.max_children'] ?? null), 'pm.max_children'];
    }

    /**
     * FrankenPHP's concurrency ceiling is its thread count in classic mode and
     * its worker count in worker mode. Octane sets the Caddy variables when it
     * starts the server; the FRANKENPHP_* pair is what a hand-run or
     * image-configured server uses.
     *
     * @return array{0: array<string, string>, 1: int|null, 2: string|null}
     */
    protected function frankenphp(): array
    {
        $settings = $this->fromEnvironment([
            'num_threads' => 'FRANKENPHP_NUM_THREADS',
            'num_workers' => 'FRANKENPHP_NUM_WORKERS',
            'worker_count' => 'CADDY_SERVER_WORKER_COUNT',
            'max_requests' => 'MAX_REQUESTS',
            'max_execution_time' => 'REQUEST_MAX_EXECUTION_TIME',
        ]);

        foreach (['worker_count', 'num_workers', 'num_threads'] as $key) {
            if ($workers = $this->integer($settings[$key] ?? null)) {
                return [$settings, $workers, $key];
            }
        }

        return [$settings, null, null];
    }

    /**
     * Swoole and RoadRunner receive their worker counts through a config file
     * Octane writes, not through the environment, so there is usually nothing
     * to read here. Reporting the run limits it does export is still worth
     * more than reporting nothing.
     *
     * @return array{0: array<string, string>, 1: int|null, 2: string|null}
     */
    protected function octane(): array
    {
        $settings = $this->fromEnvironment([
            'max_requests' => 'MAX_REQUESTS',
            'max_execution_time' => 'REQUEST_MAX_EXECUTION_TIME',
        ]);

        return [$settings, null, null];
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    protected function fromEnvironment(array $map): array
    {
        $settings = [];

        foreach ($map as $key => $variable) {
            if ($value = $this->clean(getenv($variable) ?: null)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * The web server in front of PHP, which php_sapi_name() cannot see: it
     * answers "fpm-fcgi" for nginx and Apache alike. SERVER_SOFTWARE is a
     * CGI/1.1 variable that both pass over FastCGI ("nginx/1.27.3",
     * "Apache/2.4.62 (Debian)"), so it is the one thing that tells the
     * fpm-nginx and fpm-apache images apart at runtime.
     *
     * Name and version are split rather than published as one string because
     * the community validator's privacy scan reads a four-part version as an
     * IP address, and there is no reason to hand it something that looks like
     * one.
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function frontEnd(): array
    {
        $software = $_SERVER['SERVER_SOFTWARE'] ?? (getenv('SERVER_SOFTWARE') ?: null);

        if (! is_string($software) || trim($software) === '') {
            return [null, null];
        }

        if (! preg_match('#^([A-Za-z][A-Za-z0-9_+-]{0,29})(?:/([0-9][0-9A-Za-z.-]{0,19}))?#', trim($software), $matches)) {
            return [null, null];
        }

        $name = strtolower($matches[1]);

        return [self::FRONT_ENDS[$name] ?? $name, $matches[2] ?? null];
    }

    /**
     * @return array<int, string>
     */
    protected function poolFiles(): array
    {
        return array_merge(self::POOL_PATHS, glob('/etc/php/*/fpm/pool.d/www.conf') ?: []);
    }

    protected function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match(self::VALUE_PATTERN, $value) === 1 ? $value : null;
    }

    protected function integer(?string $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($integer) && $integer > 0 ? $integer : null;
    }
}
