<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloudflareSpeedTestRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'network_test_type' => [Rule::in(['ipv4', 'ipv6'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'network_test_type.in' => 'The network test type must be ipv4 or ipv6.',
        ];
    }
}
