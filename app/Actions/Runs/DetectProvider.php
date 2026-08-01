<?php

namespace App\Actions\Runs;

use App\Actions\Results\CloudflareSpeedTestResults;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Best zero-config guess at who is hosting this server: the RIPE holder for
 * the ASN the speed test observed (e.g. "DIGITALOCEAN-ASN").
 *
 * Any failure — the network stage was not run, the endpoint is down, no
 * outbound access — degrades to null. The user can always set the host
 * manually on the run page, so this is a convenience, never a dependency.
 */
class DetectProvider
{
    public function execute(): ?string
    {
        try {
            $asn = (new CloudflareSpeedTestResults)->execute()['asn'] ?? null;

            if (! $asn) {
                return null;
            }

            $response = Http::timeout(5)->get('https://stat.ripe.net/data/as-overview/data.json', [
                'resource' => "AS{$asn}",
            ]);

            return $response->json('data.holder') ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
