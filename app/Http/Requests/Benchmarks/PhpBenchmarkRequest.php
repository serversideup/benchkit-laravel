<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhpBenchmarkRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => [Rule::in(['full', 'quick'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => 'The mode must be full or quick.',
        ];
    }
}
