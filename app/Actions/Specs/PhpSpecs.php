<?php

namespace App\Actions\Specs;

class PhpSpecs
{
    public function execute()
    {
        return [
            'php_server_api' => $this->getPhpServerApi(),
            'memory_limit' => $this->getMemoryLimit(),
            'op_cache' => $this->getOpCache(),
        ];
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
}