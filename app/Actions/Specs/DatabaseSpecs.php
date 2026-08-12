<?php

namespace App\Actions\Specs;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * What the database was configured to guarantee while the PHP benchmarks ran.
 *
 * The CRUD subjects commit one statement at a time, so durability settings
 * dominate them: the same box writing to the same disk reports numbers orders
 * of magnitude apart depending on whether a commit waits for the disk. Without
 * these values on the run, a slow write result is unattributable — nobody
 * reading it can tell a slow disk from a database told not to wait for one.
 *
 * The filesystem type is recorded for the same reason and the path is not: a
 * database on tmpfs is measuring RAM, and that is worth publishing, while the
 * path it sits at identifies the machine and is not.
 */
class DatabaseSpecs
{
    /**
     * SQLite reports `synchronous` as an integer. The names are what the
     * setting is called everywhere else, including in Laravel's own config.
     */
    protected const SQLITE_SYNCHRONOUS = [0 => 'off', 1 => 'normal', 2 => 'full', 3 => 'extra'];

    /**
     * @return array{driver: ?string, version: ?string, filesystem: ?string, durability: array<string, mixed>}
     */
    public function execute(): array
    {
        // Each value degrades on its own. A run that never reached the
        // database still produces a valid document, and the driver is worth
        // reporting even when the server behind it cannot be asked anything.
        $driver = $this->attempt(fn (Connection $connection) => $connection->getDriverName());

        return [
            'driver' => $driver,
            'version' => $this->version(),
            'filesystem' => $driver === 'sqlite' ? $this->filesystemType($this->databaseName()) : null,
            'durability' => $driver === null ? [] : $this->durability($driver),
        ];
    }

    /**
     * The settings that decide whether a commit waits for durable storage,
     * named as each engine names them. Unknown drivers report nothing rather
     * than a guess.
     *
     * @return array<string, mixed>
     */
    protected function durability(string $driver): array
    {
        return match ($driver) {
            'sqlite' => [
                'journal_mode' => $this->lower($this->scalar('PRAGMA journal_mode')),
                'synchronous' => $this->sqliteSynchronous(),
            ],
            'mysql', 'mariadb' => [
                'innodb_flush_log_at_trx_commit' => $this->variable('innodb_flush_log_at_trx_commit'),
                'sync_binlog' => $this->variable('sync_binlog'),
            ],
            'pgsql' => [
                'synchronous_commit' => $this->lower($this->scalar('SHOW synchronous_commit')),
                'fsync' => $this->lower($this->scalar('SHOW fsync')),
            ],
            default => [],
        };
    }

    protected function version(): ?string
    {
        return $this->attempt(function (Connection $connection): ?string {
            $version = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

            return is_scalar($version) ? (string) $version : null;
        });
    }

    protected function databaseName(): string
    {
        return $this->attempt(fn (Connection $connection) => $connection->getDatabaseName()) ?? '';
    }

    /**
     * Ask the connection something, and report nothing rather than failing the
     * run when it cannot answer.
     *
     * @template T
     *
     * @param  callable(Connection): T  $question
     * @return T|null
     */
    protected function attempt(callable $question): mixed
    {
        try {
            return $question(DB::connection());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The filesystem the SQLite file sits on — "tmpfs" and "ext4" produce very
     * different write results and neither is wrong, only different. Linux
     * only, by design: this reads the same /proc-era tooling ServerSpecs does,
     * and returns null anywhere it is unavailable.
     */
    protected function filesystemType(string $path): ?string
    {
        if ($path === '' || $path === ':memory:') {
            return $path === ':memory:' ? 'memory' : null;
        }

        $type = trim((string) shell_exec('stat -f -c %T '.escapeshellarg($path).' 2>/dev/null'));

        return $type !== '' ? $type : null;
    }

    /**
     * First column of the first row, or null when the statement is not
     * supported by the server the app happens to be pointed at.
     */
    protected function scalar(string $statement): ?string
    {
        try {
            $row = DB::selectOne($statement);

            $value = $row === null ? null : array_values((array) $row)[0] ?? null;

            return is_scalar($value) ? (string) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function variable(string $name): ?string
    {
        try {
            $row = DB::selectOne('SHOW VARIABLES LIKE ?', [$name]);

            return isset($row->Value) && is_scalar($row->Value) ? (string) $row->Value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A missing answer is not level 0. Casting an unreachable database's silence
     * to an integer would publish "off" — the setting most likely to be blamed
     * for a fast write result — about a database nobody asked.
     */
    protected function sqliteSynchronous(): ?string
    {
        $level = $this->scalar('PRAGMA synchronous');

        return $level === null ? null : (self::SQLITE_SYNCHRONOUS[(int) $level] ?? null);
    }

    protected function lower(?string $value): ?string
    {
        return $value === null ? null : strtolower($value);
    }
}
