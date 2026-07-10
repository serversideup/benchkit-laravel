<?php

namespace App\Support;

class CfSpeedTestCommand
{
    public function build(string $networkTestType): string
    {
        $flag = $networkTestType === 'ipv6' ? ' --ipv6' : ' --ipv4';

        return base_path('vendor/bin/cfspeedtest').$flag;
    }
}
