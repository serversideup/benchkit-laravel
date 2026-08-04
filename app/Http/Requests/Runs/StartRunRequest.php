<?php

namespace App\Http\Requests\Runs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The settings for a whole run, validated in one place now that the run is
 * started as a queue rather than a stage at a time. The per-stage rules
 * this replaces lived in app/Http/Requests/Benchmarks.
 */
class StartRunRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.hardware' => ['boolean'],
            'settings.disk' => ['boolean'],
            'settings.geekbench' => ['boolean'],
            'settings.geekbench_version' => ['integer', Rule::in([4, 5, 6])],
            'settings.iperf' => ['boolean'],
            'settings.network' => ['boolean'],
            'settings.network_test_type' => [Rule::in(['ipv4', 'ipv6'])],
            'settings.http' => ['boolean'],
            'settings.http_duration' => ['integer', 'between:5,60'],
            'settings.http_connections' => ['integer', 'between:1,500'],
            'settings.http_io_ms' => ['integer', 'between:0,1000'],
            'settings.php_database' => ['boolean'],
            'settings.php_mode' => [Rule::in(['full', 'quick'])],

            'preset' => ['nullable', 'string', Rule::in(['quick', 'full', 'custom'])],

            'host_details' => ['nullable', 'array'],
            'host_details.provider' => ['nullable', 'string', 'max:120'],
            'host_details.plan' => ['nullable', 'string', 'max:120'],
            'host_details.datacenter' => ['nullable', 'string', 'max:120'],
            'host_details.cost' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'The settings for the run are required.',
            'settings.geekbench_version.in' => 'The Geekbench version must be 4, 5, or 6.',
            'settings.network_test_type.in' => 'The network test type must be ipv4 or ipv6.',
            'settings.http_duration.between' => 'The load test duration must be between 5 and 60 seconds.',
            'settings.http_connections.between' => 'The connection count must be between 1 and 500.',
            'settings.http_io_ms.between' => 'The simulated I/O response must be between 0 and 1000 milliseconds.',
            'settings.php_mode.in' => 'The PHP mode must be full or quick.',
        ];
    }
}
