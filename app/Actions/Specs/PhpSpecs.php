<?php

namespace App\Actions\Specs;

class PhpSpecs
{
    /**
     * The php.ini directives that actually move benchmark numbers. A curated
     * list rather than ini_get_all() — the point is a readable, reproducible
     * record of the tuning that produced a run, not 200 directives of noise.
     *
     * Public because BuildSubmissionDocument publishes this list minus its own
     * withheld keys, rather than keeping a second copy that could drift.
     *
     * @var array<int, string>
     */
    public const INI_KEYS = [
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
     * Directives BenchKit records for its own use but never hands out.
     *
     * opcache.preload is a filesystem path, which would expose the operator's
     * directory layout and often a project or company name. Whether preloading
     * is on is the part that explains a number, and that ships separately as a
     * boolean. Kept here rather than at each consumer so that a directive added
     * to INI_KEYS above cannot start leaking through one caller that forgot.
     *
     * @var array<int, string>
     */
    public const PRIVATE_INI_KEYS = ['opcache.preload'];

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
            'runtime' => (new ServingRuntime)->execute(),
        ];
    }

    /**
     * The same snapshot with the private directives removed, for anywhere it
     * leaves this process — the /bench/env endpoint on a live instance, and the
     * run document that reaches the public gallery.
     *
     * @return array<string, mixed>
     */
    public function publicSnapshot(): array
    {
        $specs = $this->execute();

        $specs['ini'] = array_diff_key($specs['ini'], array_flip(self::PRIVATE_INI_KEYS));

        return $specs;
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

}
