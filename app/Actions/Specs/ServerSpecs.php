<?php

namespace App\Actions\Specs;

class ServerSpecs
{
    /**
     * @return array<string, string>
     */
    public function execute(): array
    {
        return [
            'cpu_model' => $this->cpuModel(),
            'cpu_cores' => $this->cpuCores(),
            'cpu_frequency' => $this->cpuFrequency(),
            'os' => $this->os(),
            'ram' => $this->ram(),
        ];
    }

    protected function cpuModel(): string
    {
        // POSIX sh only — shell_exec runs via /bin/sh (dash on Debian),
        // where bash-isms like [[ ]] or $OSTYPE fail
        $command = <<<'CMD'
        if cpu_info=$(grep -m1 "model name" /proc/cpuinfo 2>/dev/null); then
            echo "$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
        elif [ "$(uname)" = "Darwin" ]; then
            sysctl -n machdep.cpu.brand_string 2>/dev/null
        else
            echo "Unknown Processor Model"
        fi
        CMD;

        return $this->shell($command);
    }

    protected function cpuCores(): string
    {
        $command = <<<'CMD'
        [ -f /proc/cpuinfo ] && grep -c "^processor" /proc/cpuinfo || sysctl -n hw.ncpu 2>/dev/null
        CMD;

        return $this->shell($command);
    }

    protected function cpuFrequency(): string
    {
        $command = <<<'CMD'
        if cpu_info=$(grep -m1 "cpu MHz" /proc/cpuinfo 2>/dev/null); then
            echo "$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
        else
            echo "Unknown CPU Frequency"
        fi
        CMD;

        return $this->shell($command);
    }

    protected function os(): string
    {
        return $this->shell('grep "^PRETTY_NAME=" /etc/os-release | cut -d\'"\' -f2');
    }

    protected function ram(): string
    {
        $ram = (int) $this->shell('awk \'/MemTotal/ {print $2}\' /proc/meminfo');

        return round($ram / 1024, 3).' MB';
    }

    /**
     * shell_exec returns null when the command cannot run (restricted hosts,
     * missing binaries) — normalize to an empty string.
     */
    protected function shell(string $command): string
    {
        return trim((string) shell_exec($command));
    }
}
