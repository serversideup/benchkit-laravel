<?php

namespace App\Support\Http;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;

/**
 * Why the DB read target could not reach its table, kept where the results
 * page can read it.
 *
 * oha records status codes and nothing else, so the 503 the route answers
 * with cannot carry its reason. The web process leaves one marker per cause
 * in the results directory instead, and the parser reads them back beside the
 * level that broke. A marker rather than a counter: the level's counts are
 * already in oha's distribution, and a counter would be a lock contended by
 * every failing request inside the window being measured.
 */
class DatabaseFailure
{
    /** The app ran out of ephemeral ports to open database connections with. */
    public const PORTS_EXHAUSTED = 'ports_exhausted';

    /** The database refused connections past its own limit. */
    public const CONNECTIONS_EXHAUSTED = 'connections_exhausted';

    /** The database could not be reached at all. */
    public const UNREACHABLE = 'unreachable';

    /** The benchmark table was not there to read. */
    public const MISSING_TABLE = 'missing_table';

    public const OTHER = 'other';

    /**
     * Driver messages that name each cause, in the order they are tried. A
     * missing table is read from its SQLSTATE instead, which every driver
     * reports the same way.
     *
     * @var array<string, array<int, string>>
     */
    protected const MESSAGES = [
        self::PORTS_EXHAUSTED => ['Cannot assign requested address'],
        self::CONNECTIONS_EXHAUSTED => ['too many clients', 'Too many connections', 'remaining connection slots'],
        self::UNREACHABLE => ['Connection refused', 'No route to host', 'timed out', 'Name or service not known', 'could not translate host name'],
    ];

    /** SQLSTATE classes for an undefined table, MySQL's and Postgres's. */
    protected const MISSING_TABLE_STATES = ['42S02', '42P01'];

    public static function classify(QueryException $exception): string
    {
        $message = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        if (preg_match('/SQLSTATE\[(\w{5})\]/', $message, $state) && in_array($state[1], self::MISSING_TABLE_STATES, true)) {
            return self::MISSING_TABLE;
        }

        if (str_contains($message, 'no such table')) {
            return self::MISSING_TABLE;
        }

        foreach (self::MESSAGES as $cause => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($message, $needle)) {
                    return $cause;
                }
            }
        }

        return self::OTHER;
    }

    /**
     * Leave the marker for a cause, once. Every request after the first pays
     * a single stat and nothing else.
     */
    public function record(string $cause): void
    {
        $path = $this->path($cause);

        if (file_exists($path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));

        $handle = @fopen($path, 'x');

        if ($handle !== false) {
            fwrite($handle, json_encode(['cause' => $cause]));
            fclose($handle);
        }
    }

    /**
     * Every cause seen since the results directory was last cleared, oldest
     * first, so the one the sweep hit first is the one the caveat explains.
     *
     * @return array<int, string>
     */
    public function recorded(): array
    {
        $paths = File::glob($this->path('*'));

        usort($paths, fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));

        return array_values(array_map(
            fn (string $path): string => substr(basename($path, '.json'), strlen(self::PREFIX)),
            $paths,
        ));
    }

    /**
     * Named to fall inside the http-db-read-*.json glob that clears a route's
     * levels, so a marker never outlives the run that left it.
     */
    protected const PREFIX = 'http-db-read-cause-';

    protected function path(string $cause): string
    {
        return config('benchmark.results_path').'/'.self::PREFIX.$cause.'.json';
    }
}
