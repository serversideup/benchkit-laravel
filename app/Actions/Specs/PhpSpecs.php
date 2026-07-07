<?php

namespace App\Actions\Specs;

class PhpSpecs
{
    public function execute()
    {
        return [
            'php_version' => $this->getPhpVersion(),
            'php_server_api' => $this->getPhpServerApi(),
            'php_variation' => $this->getPhpVariation(),
            'octane' => $this->isRunningOctane(),
            'memory_limit' => $this->getMemoryLimit(),
            'op_cache' => $this->getOpCache(),
            'op_cache_jit' => $this->getOpCacheJit(),
            'op_cache_jit_buffer_size' => $this->getOpCacheJitBufferSize(),
        ];
    }

    public function getPhpVariation(): ?string
    {
        return config('benchmark.php_variation');
    }

    public function isRunningOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) || getenv('LARAVEL_OCTANE') !== false;
    }

    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    public function getPhpServerApi()
    {
        return php_sapi_name();
    }

    public function getMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    public function getOpCache()
    {
        return ini_get('opcache.enable');
    }

    public function getOpCacheJit(): string|false
    {
        return ini_get('opcache.jit');
    }

    public function getOpCacheJitBufferSize(): string|false
    {
        return ini_get('opcache.jit_buffer_size');
    }
}
