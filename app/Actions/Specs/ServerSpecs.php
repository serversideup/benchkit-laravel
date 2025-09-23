<?php

namespace App\Actions\Specs;

class ServerSpecs
{
    public function execute()
    {
        return [
            'cpu_model' => $this->getCpuModel(),
            'cpu_cores' => $this->getCpuCores(),
            'cpu_frequency' => $this->getCpuFrequency(),
            'os' => $this->getOs(),
            'ram' => $this->getRam(),
        ];
    }

    public function getCpuModel()
    {
        return trim(shell_exec('lscpu | grep "Model name:" | cut -d":" -f2'));
    }

    public function getCpuCores()
    {
        return trim(shell_exec('lscpu | grep "CPU(s):" | awk \'{print $2}\''));
    }

    public function getCpuFrequency()
    {
        return trim(shell_exec('lscpu | grep "CPU MHz:" | awk \'{print $3}\''));
    }

    public function getOs()
    {
        return trim(shell_exec('grep "^PRETTY_NAME=" /etc/os-release | cut -d\'"\' -f2'));
    }

    public function getRam()
    {
        $ram = (int) trim(shell_exec('awk \'/MemTotal/ {print $2}\' /proc/meminfo'));
        return round($ram / 1024, 3) . ' MB';
    }

}