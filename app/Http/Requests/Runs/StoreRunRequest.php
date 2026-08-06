<?php

namespace App\Http\Requests\Runs;

use App\Actions\Runs\CreateRunSnapshot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRunRequest extends FormRequest
{
    use ValidatesHostCost;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'stages_completed' => ['required', 'array', 'min:1'],
            'stages_completed.*' => ['string', Rule::in(CreateRunSnapshot::STAGES)],
            'settings' => ['required', 'array'],
            'preset' => ['nullable', 'string', Rule::in(['quick', 'full', 'custom'])],
            'provider' => ['nullable', 'string', 'max:120'],
            'provider_source' => ['nullable', 'string', Rule::in(['ripe', 'user'])],
            'plan' => ['nullable', 'string', 'max:120'],
            'datacenter' => ['nullable', 'string', 'max:120'],
            'cost' => ['nullable', 'array'],
            ...self::costRules(),
            'logs' => ['nullable', 'array'],
            'logs.*' => ['array', 'max:20000'],
            'logs.*.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stages_completed.required' => 'At least one completed benchmark stage is required to save a run.',
            'stages_completed.min' => 'At least one completed benchmark stage is required to save a run.',
            'stages_completed.*.in' => 'Unknown benchmark stage.',
            'settings.required' => 'The settings used for the run are required.',
            ...self::costMessages(),
        ];
    }
}
