<?php

namespace App\Support;

/**
 * The GitHub issue a submission arrives as.
 *
 * The round trip through an issue is deliberate: it credits the submission to
 * the submitter's GitHub account without BenchKit needing accounts, logins, or
 * a server of its own, and every result in the gallery lands through a
 * reviewable pull request. A bot
 * (.github/workflows/action_process-result-submission.yml) reads the token,
 * records the issue author as the submitter, validates, opens the PR, and
 * closes the issue.
 */
class SubmissionIssue
{
    public const REPO = 'serversideup/benchkit-laravel';

    /**
     * Real bug reports never carry this, so the bot ignores everything else.
     * It has to survive the fallback body below too, or a submission that was
     * too long to pre-fill would never be picked up.
     */
    public const MARKER = '<!-- benchkit-result-submission -->';

    /**
     * Past this, GitHub starts cutting the request URI — the failure that made
     * all of this necessary. A worst-case Full run tokenises to about 2,800
     * characters, so this should never trip; it exists so that if the document
     * ever does outgrow a URL, the submitter is told to paste rather than
     * silently filing a truncated result.
     */
    public const MAX_URL_LENGTH = 6000;

    /**
     * @param  array<string, mixed>  $document
     * @return array{url: string, body: string, prefill: bool}
     */
    public static function for(array $document, string $token): array
    {
        $body = self::body($document, $token);
        $url = self::url($document, $body);

        if (strlen($url) <= self::MAX_URL_LENGTH) {
            return ['url' => $url, 'body' => $body, 'prefill' => true];
        }

        // Too long to carry: pre-fill everything except the token so the marker
        // and the summary still arrive, and let the submitter paste the token
        // from their clipboard into the empty block.
        $fallback = self::body($document, null);

        return ['url' => self::url($document, $fallback), 'body' => $fallback, 'prefill' => false];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function body(array $document, ?string $token): string
    {
        return implode("\n", [
            self::MARKER,
            '',
            self::summary($document),
            '',
            '```',
            $token ?? 'Paste your submission token here — it is on your clipboard.',
            '```',
            '',
            '_Submitted from the BenchKit app. The block above is the submission; the summary line is'
                .' for humans, and the bot reads only the token. Your GitHub username is recorded'
                .' automatically as the submitter._',
        ]);
    }

    /**
     * One scannable line so a maintainer can see what an issue holds without
     * decoding anything. Never authoritative — if it disagrees with the token,
     * the token is the submission.
     *
     * @param  array<string, mixed>  $document
     */
    public static function summary(array $document): string
    {
        $server = $document['environment']['server'] ?? [];
        $php = $document['environment']['php'] ?? [];
        $route = self::heroRoute($document);

        $parts = array_filter([
            '**'.($document['meta']['label'] ?? 'BenchKit run').'**',
            $document['meta']['provider'] ?? null,
            isset($server['cpu_cores']) ? $server['cpu_cores'].' vCPU' : null,
            isset($php['php_version']) ? 'PHP '.$php['php_version'] : null,
            isset($route['requests_per_second'])
                ? number_format((float) $route['requests_per_second']).' req/s ('.$route['key'].')'
                : null,
            isset($route['p95_ms']) ? 'p95 '.round((float) $route['p95_ms'], 2).' ms' : null,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected static function url(array $document, string $body): string
    {
        $query = http_build_query([
            'title' => 'Result: '.($document['meta']['label'] ?? 'BenchKit run'),
            'labels' => 'result-submission',
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://github.com/'.self::REPO.'/issues/new?'.$query;
    }

    /**
     * The route worth putting in the summary, in the same order of preference
     * CreateRunSnapshot uses for a run's headline number.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected static function heroRoute(array $document): array
    {
        foreach (['db_read', 'json', 'static'] as $key) {
            $route = $document['benchmarks']['http']['routes'][$key] ?? null;

            if (is_array($route) && isset($route['requests_per_second'])) {
                return [...$route, 'key' => $key];
            }
        }

        return [];
    }
}
