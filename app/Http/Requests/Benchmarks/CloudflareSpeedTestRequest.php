<?php

namespace App\Http\Requests\Benchmarks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloudflareSpeedTestRequest extends FormRequest
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
            'network_test_type' => [Rule::in(['ipv4', 'ipv6'])],
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
            'network_test_type.in' => 'The network test type must be ipv4 or ipv6.',
        ];
    }
}
