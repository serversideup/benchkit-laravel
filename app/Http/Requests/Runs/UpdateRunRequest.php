<?php

namespace App\Http\Requests\Runs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRunRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:120'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:120'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:120'],
            'datacenter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'cost' => ['sometimes', 'nullable', 'string', 'max:60'],
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
            'label.max' => 'The label may not be longer than 120 characters.',
        ];
    }
}
