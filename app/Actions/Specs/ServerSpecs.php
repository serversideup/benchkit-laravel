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
        // Run multi-line command and capture output
        $command = <<<'CMD'
        if cpu_info=$(grep -m1 "model name" /proc/cpuinfo 2>/dev/null); then
            echo "$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
        elif [[ "$OSTYPE" == "darwin"* ]]; then
            sysctl -n machdep.cpu.brand_string 2>/dev/null
        else
            echo "Unknown Processor Model"
        fi
        CMD;

        return trim(shell_exec($command));
    }

    public function getCpuCores()
    {
        $command = <<<'CMD'
        [ -f /proc/cpuinfo ] && grep -c "^processor" /proc/cpuinfo || sysctl -n hw.ncpu 2>/dev/null
        CMD;

        return trim(shell_exec($command));
    }

    public function getCpuFrequency()
    {
        $command = <<<'CMD'
        if cpu_info=$(grep -m1 "cpu MHz" /proc/cpuinfo 2>/dev/null); then
            echo "$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
        else
            echo "Unknown CPU Frequency"
        fi
        CMD;

        return trim(shell_exec($command));
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