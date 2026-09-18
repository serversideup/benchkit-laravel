<?php

namespace App\Support\Http;

/**
 * What this machine can hold open when it drives its own load.
 *
 * The external generator's script measures the same two things and reports
 * them at handshake. A self-test has no handshake, so they are measured here,
 * in the process that spawns oha, and written into the run's meta the same way.
 */
class GeneratorLimits
{
    /**
     * Raise the soft descriptor limit to the hard one. Needs no privileges,
     * and has to run in the same shell as oha because a limit is per process.
     * Mirrors the generator script.
     */
    public const RAISE = 'h=$(ulimit -Hn 2>/dev/null); case "$h" in unlimited|"") ulimit -n 65536 2>/dev/null || true ;; *) ulimit -n "$h" 2>/dev/null || true ;; esac;';

    /**
     * @return array{fd_limit: int|null, port_range: int|null}
     */
    public static function measure(): array
    {
        return [
            'fd_limit' => self::integer(self::shell(self::RAISE.' ulimit -n 2>/dev/null')),
            'port_range' => self::portRange(),
        ];
    }

    /**
     * Ephemeral ports available to one source address for one destination:
     * the kernel's range on Linux, sysctl's on macOS.
     */
    protected static function portRange(): ?int
    {
        $range = self::shell(
            "awk '{print \$2 - \$1 + 1}' /proc/sys/net/ipv4/ip_local_port_range 2>/dev/null".
            ' || { lo=$(sysctl -n net.inet.ip.portrange.first 2>/dev/null); hi=$(sysctl -n net.inet.ip.portrange.last 2>/dev/null); [ -n "$lo" ] && [ -n "$hi" ] && echo $((hi - lo + 1)); }'
        );

        return self::integer($range);
    }

    protected static function integer(string $value): ?int
    {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected static function shell(string $command): string
    {
        return trim((string) shell_exec($command));
    }
}
