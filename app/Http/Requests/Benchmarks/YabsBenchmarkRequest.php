<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class YabsBenchmarkRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'disk' => ['boolean'],
            'geekbench' => ['boolean'],
            'geekbench_version' => ['integer', Rule::in([4, 5, 6])],
            'iperf' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'geekbench_version.in' => 'The Geekbench version must be 4, 5, or 6.',
        ];
    }
}
