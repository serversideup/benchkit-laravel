<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;

class HttpBenchmarkRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'duration' => ['integer', 'between:5,60'],
            'connections' => ['integer', 'between:1,500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration.between' => 'The load test duration must be between 5 and 60 seconds.',
            'connections.between' => 'The connection count must be between 1 and 500.',
        ];
    }
}
