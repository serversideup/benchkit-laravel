<?php

namespace App\Http\Requests\Runs;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRunRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
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
}
