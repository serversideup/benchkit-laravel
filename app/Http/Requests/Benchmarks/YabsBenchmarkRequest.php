<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class YabsBenchmarkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'geekbench_version.in' => 'The Geekbench version must be 4, 5, or 6.',
        ];
    }
}
