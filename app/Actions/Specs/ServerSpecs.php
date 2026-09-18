<?php

namespace App\Actions\Specs;

class ServerSpecs
{
    /**
     * Paths are injectable so a test can stand in fake /proc and cgroup files.
     */
    public function __construct(
        protected string $procPath = '/proc',
        protected string $cgroupPath = '/sys/fs/cgroup',
    ) {}

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
        $command = <<<CMD
        if cpu_info=\$(grep -m1 "model name" {$this->procPath}/cpuinfo 2>/dev/null); then
            echo "\$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
        elif [ "\$(uname)" = "Darwin" ]; then
            sysctl -n machdep.cpu.brand_string 2>/dev/null
        else
            echo "Unknown Processor Model"
        fi
        CMD;

        return $this->shell($command);
    }

    /**
     * The cores this process may actually use. /proc/cpuinfo is not
     * namespaced, so inside a container it lists the host's cores; a cgroup
     * CPU quota is the real ceiling and wins when it is lower.
     */
    protected function cpuCores(): string
    {
        $command = <<<CMD
        [ -f {$this->procPath}/cpuinfo ] && grep -c "^processor" {$this->procPath}/cpuinfo || sysctl -n hw.ncpu 2>/dev/null
        CMD;

        $cores = $this->shell($command);
        $limit = $this->cgroupCpuLimit();

        if ($limit !== null && is_numeric($cores) && $limit < (int) $cores) {
            return (string) $limit;
        }

        return $cores;
    }

    protected function cpuFrequency(): string
    {
        $command = <<<CMD
        if cpu_info=\$(grep -m1 "cpu MHz" {$this->procPath}/cpuinfo 2>/dev/null); then
            echo "\$cpu_info" | cut -d':' -f2- | sed 's/^ *//'
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

    /**
     * Memory this process may actually use, in MiB (labelled "MB", which is
     * what every consumer parses). MemTotal is the host's; a cgroup memory
     * limit wins when it is lower. Empty when neither could be read.
     */
    protected function ram(): string
    {
        $kilobytes = $this->shell("awk '/MemTotal/ {print \$2}' {$this->procPath}/meminfo 2>/dev/null");

        if (! is_numeric($kilobytes) || (int) $kilobytes <= 0) {
            return '';
        }

        $megabytes = (int) $kilobytes / 1024;
        $limit = $this->cgroupMemoryLimitBytes();

        if ($limit !== null && $limit / 1024 ** 2 < $megabytes) {
            $megabytes = $limit / 1024 ** 2;
        }

        return round($megabytes, 3).' MB';
    }

    /**
     * Whole cores allowed by a cgroup CPU quota, or null when unlimited or
     * unreadable. cgroup v2 writes "quota period" (or "max period") to
     * cpu.max; v1 splits the same two numbers across two files.
     */
    protected function cgroupCpuLimit(): ?int
    {
        $max = $this->readFile("{$this->cgroupPath}/cpu.max");

        if ($max !== null) {
            [$quota, $period] = array_pad(preg_split('/\s+/', $max), 2, null);
        } else {
            $quota = $this->readFile("{$this->cgroupPath}/cpu/cpu.cfs_quota_us");
            $period = $this->readFile("{$this->cgroupPath}/cpu/cpu.cfs_period_us");
        }

        if (! is_numeric($quota) || ! is_numeric($period) || (int) $quota <= 0 || (int) $period <= 0) {
            return null;
        }

        return max(1, (int) ceil((int) $quota / (int) $period));
    }

    /**
     * Bytes allowed by a cgroup memory limit, or null when unlimited or
     * unreadable. v2 writes "max" when unlimited; v1 writes a number near
     * PHP_INT_MAX, which is caught by the same comparison against MemTotal.
     */
    protected function cgroupMemoryLimitBytes(): ?int
    {
        $limit = $this->readFile("{$this->cgroupPath}/memory.max")
            ?? $this->readFile("{$this->cgroupPath}/memory/memory.limit_in_bytes");

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    protected function readFile(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : trim($contents);
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
