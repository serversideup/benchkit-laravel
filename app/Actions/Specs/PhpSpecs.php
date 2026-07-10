<?php

namespace App\Actions\Specs;

class PhpSpecs
{
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
}
