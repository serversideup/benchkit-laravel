<?php

namespace App\Actions\Runs;

use Illuminate\Support\Facades\Storage;

class UpdateRunMeta
{
    /**
     * @param  array{label?: string, provider?: ?string, plan?: ?string, datacenter?: ?string, cost?: ?string}  $attributes
     * @return array<string, mixed>|null
     */
    public function execute(string $id, array $attributes): ?array
    {
        $snapshot = (new FindRun)->execute($id);

        if ($snapshot === null) {
            return null;
        }

        if (array_key_exists('label', $attributes)) {
            $snapshot['meta']['label'] = $attributes['label'];
        }

        if (array_key_exists('provider', $attributes)) {
            $snapshot['meta']['provider'] = $attributes['provider'];
            $snapshot['meta']['provider_source'] = $attributes['provider'] !== null ? 'user' : null;
        }

        foreach (['plan', 'datacenter', 'cost'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $snapshot['meta'][$field] = $attributes[$field];
            }
        }

        Storage::disk('runs')->put("{$id}.json", json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $snapshot;
    }
}
