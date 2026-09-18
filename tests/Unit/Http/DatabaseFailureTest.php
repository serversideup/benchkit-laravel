<?php

namespace Tests\Unit\Http;

use App\Support\Http\DatabaseFailure;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesFakeResultsPath;
use Tests\TestCase;

class DatabaseFailureTest extends TestCase
{
    use UsesFakeResultsPath;

    protected function failing(string $message): QueryException
    {
        return new QueryException('pgsql', 'select 1', [], new PDOException($message));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function driverMessages(): array
    {
        return [
            'postgres out of ports' => ['SQLSTATE[08006] [7] connection to server at "postgres" (172.18.0.2), port 5432 failed: Cannot assign requested address', DatabaseFailure::PORTS_EXHAUSTED],
            'mysql out of ports' => ['SQLSTATE[HY000] [2002] Cannot assign requested address', DatabaseFailure::PORTS_EXHAUSTED],
            'postgres at its limit' => ['SQLSTATE[08006] [7] FATAL:  sorry, too many clients already', DatabaseFailure::CONNECTIONS_EXHAUSTED],
            'postgres reserved slots' => ['SQLSTATE[08006] [7] FATAL:  remaining connection slots are reserved for roles with the SUPERUSER attribute', DatabaseFailure::CONNECTIONS_EXHAUSTED],
            'mysql at its limit' => ['SQLSTATE[HY000] [1040] Too many connections', DatabaseFailure::CONNECTIONS_EXHAUSTED],
            'nothing listening' => ['SQLSTATE[HY000] [2002] Connection refused', DatabaseFailure::UNREACHABLE],
            'host unknown' => ['SQLSTATE[08006] [7] could not translate host name "postgres" to address: Name or service not known', DatabaseFailure::UNREACHABLE],
            'postgres table gone' => ['SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "benchmark_http_items" does not exist', DatabaseFailure::MISSING_TABLE],
            'mysql table gone' => ["SQLSTATE[42S02]: Base table or view not found: 1146 Table 'benchkit.benchmark_http_items' doesn't exist", DatabaseFailure::MISSING_TABLE],
            'sqlite table gone' => ['SQLSTATE[HY000]: General error: 1 no such table: benchmark_http_items', DatabaseFailure::MISSING_TABLE],
            'anything else' => ['SQLSTATE[HY000]: General error: 5 database is locked', DatabaseFailure::OTHER],
        ];
    }

    #[DataProvider('driverMessages')]
    public function test_it_names_the_cause_from_the_drivers_own_words(string $message, string $cause): void
    {
        $this->assertSame($cause, DatabaseFailure::classify($this->failing($message)));
    }

    public function test_it_leaves_one_marker_per_cause_in_the_order_they_were_first_seen(): void
    {
        $failure = new DatabaseFailure;

        $failure->record(DatabaseFailure::PORTS_EXHAUSTED);
        touch($this->resultsPath.'/http-db-read-cause-ports_exhausted.json', time() - 10);
        $failure->record(DatabaseFailure::UNREACHABLE);
        $failure->record(DatabaseFailure::PORTS_EXHAUSTED);

        $this->assertSame([DatabaseFailure::PORTS_EXHAUSTED, DatabaseFailure::UNREACHABLE], $failure->recorded());
        $this->assertCount(2, glob($this->resultsPath.'/http-db-read-cause-*.json'), 'A repeated cause must not leave a second marker.');
    }

    public function test_nothing_recorded_reads_as_nothing(): void
    {
        $this->assertSame([], (new DatabaseFailure)->recorded());
    }
}
