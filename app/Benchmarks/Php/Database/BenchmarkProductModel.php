<?php

namespace App\Benchmarks\Php\Database;

use Illuminate\Database\Eloquent\Model;

class BenchmarkProductModel extends Model
{
    protected $table = 'benchmark_products';

    protected $fillable = [
        'name',
        'price',
        'stock',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
