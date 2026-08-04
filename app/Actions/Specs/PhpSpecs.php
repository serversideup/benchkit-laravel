<?php

namespace App\Actions\Specs;

class PhpSpecs
{
    /**
     * The php.ini directives that actually move benchmark numbers. A curated
     * list rather than ini_get_all() — the point is a readable, reproducible
     * record of the tuning that produced a run, not 200 directives of noise.
     *
     * @var array<int, string>
     */
    protected const INI_KEYS = [
        'opcache.enable',
        'opcache.enable_cli',
        'opcache.jit',
        'opcache.jit_buffer_size',
        'opcache.memory_consumption',
        'opcache.max_accelerated_files',
        'opcache.validate_timestamps',
        'opcache.revalidate_freq',
        'opcache.preload',
        'memory_limit',
        'max_execution_time',
        'realpath_cache_size',
        'realpath_cache_ttl',
        'zend.assertions',
    ];

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_server_api' => php_sapi_name(),
            'php_variation' => config('benchmark.php_variation'),
            'octane' => $this->isRunningOctane(),
            'memory_limit' => ini_get('memory_limit'),
            'op_cache' => ini_get('opcache.enable'),
            'op_cache_jit' => ini_get('opcache.jit'),
            'op_cache_jit_buffer_size' => ini_get('opcache.jit_buffer_size'),
            'ini' => $this->iniSnapshot(),
            'serving' => $this->servingConfig(),
        ];
    }

    /**
     * Octane sets LARAVEL_OCTANE in the worker environment at runtime — after
     * config may already have been cached (e.g. during a Docker image build) —
     * so it must be read directly rather than through config.
     */
    protected function isRunningOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) || getenv('LARAVEL_OCTANE') !== false;
    }

    /**
     * The performance-relevant php.ini values in effect for this run, so a
     * result is reproducible and the "which knob moved the number" question
     * has an answer sitting next to the number.
     *
     * @return array<string, string|false>
     */
    protected function iniSnapshot(): array
    {
        $snapshot = [];

        foreach (self::INI_KEYS as $key) {
            $snapshot[$key] = ini_get($key);
        }

        return $snapshot;
    }

    /**
     * Best-effort serving-daemon config. Unlike the php.ini snapshot above
     * (read via ini_get(), so available on every platform — Railway, Laravel
     * Cloud, bare metal, any image), FPM pool sizing lives in a pool .conf
     * file whose path differs by image and distro. We probe the common
     * layouts — plain php:fpm, Forge/Ploi (Debian), RHEL, and our serversideup
     * images alike — plus the serversideup env vars. A missing value means
     * "not detected" (a FrankenPHP/Octane image has no FPM pool; a fully
     * managed PaaS may not expose one), never a fabricated default.
     *
     * @return array<string, string>
     */
    protected function servingConfig(): array
    {
        $config = array_filter([
            'fpm_pm' => getenv('PHP_FPM_PM_CONTROL') ?: null,
            'fpm_max_children' => getenv('PHP_FPM_PM_MAX_CHILDREN') ?: null,
        ], fn ($value) => $value !== null);

        $pools = array_merge([
            '/usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf',
            '/usr/local/etc/php-fpm.d/www.conf',
            '/etc/php-fpm.d/www.conf',
        ], glob('/etc/php/*/fpm/pool.d/www.conf') ?: []);

        foreach ($pools as $pool) {
            if (isset($config['fpm_pm'], $config['fpm_max_children'])) {
                break;
            }

            if (! is_readable($pool)) {
                continue;
            }

            $contents = (string) file_get_contents($pool);

            if (! isset($config['fpm_pm']) && preg_match('/^\s*pm\s*=\s*(\S+)/m', $contents, $matches)) {
                $config['fpm_pm'] = $matches[1];
            }

            if (! isset($config['fpm_max_children']) && preg_match('/^\s*pm\.max_children\s*=\s*(\d+)/m', $contents, $matches)) {
                $config['fpm_max_children'] = $matches[1];
            }
        }

        return $config;
    }
}
