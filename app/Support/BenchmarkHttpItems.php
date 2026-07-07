<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owns the deterministic dataset queried by the /bench/db-read HTTP
 * benchmark target. Like the phpbench tables, this table intentionally
 * has no migration — it is created on demand for benchmark runs.
 */
class BenchmarkHttpItems
{
    public const TABLE = 'benchmark_http_items';

    public const ROWS = 50;

    public static function ensure(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('value');
                $table->boolean('is_active')->index();
                $table->timestamps();
            });
        }

        if (DB::table(self::TABLE)->count() === self::ROWS) {
            return;
        }

        DB::table(self::TABLE)->delete();

        $records = [];
        for ($i = 0; $i < self::ROWS; $i++) {
            $records[] = [
                'id' => $i + 1,
                'name' => "Item {$i}",
                'value' => ($i * 7) % 100,
                'is_active' => $i % 2 === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table(self::TABLE)->insert($records);
    }
}
