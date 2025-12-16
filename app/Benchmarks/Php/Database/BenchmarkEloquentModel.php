<?php

namespace App\Benchmarks\Php\Database;

use Illuminate\Database\Eloquent\Model;

class BenchmarkEloquentModel extends Model
{
    protected $table = 'benchmark_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'created_at',
        'updated_at',
    ];
}