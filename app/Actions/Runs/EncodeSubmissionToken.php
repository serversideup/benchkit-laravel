<?php

namespace App\Actions\Runs;

/**
 * Packs a submission document into a single line that survives a URL.
 *
 *     bk1.<base64url(deflate-raw(json))>.<sha256(json)[0:16]>
 *
 * Submissions travel as a pre-filled GitHub issue, and GitHub cuts the request
 * URI somewhere around 6-8 KB. Sending the document as pretty-printed JSON blew
 * that on a *Quick* run — 7,121 characters, truncated mid-number — so results
 * arrived corrupt with nothing to notice it. Two things fix that at once:
 * deflate shrinks the document ~5x, and base64url costs nothing to
 * percent-encode (every character is already unreserved), where raw JSON pays
 * roughly 40% for its braces, quotes, and newlines. A worst-case Full run comes
 * out around 2,800 characters.
 *
 * The digest is the same bytes hashed with SHA-256, truncated to 64 bits. Its
 * job is tamper-*evidence*, not authenticity: BenchKit is open source and
 * self-hosted, so anyone willing to run this encoder can produce whatever they
 * like. What it does rule out is the whole class of low-effort damage — a
 * truncated URL, a mangled paste, a number edited by hand in the issue body —
 * and it lets the bot say "this was modified after the app generated it"
 * instead of "invalid JSON".
 *
 * The decoding half lives in docs/shared/submission/token.mjs. PHP's gzdeflate
 * and Node's inflateRaw are both RFC 1951, and the digest is taken over the
 * transmitted bytes on both sides, so nothing depends on the two languages
 * agreeing about how to print a float.
 */
class EncodeSubmissionToken
{
    /** Bump when the payload encoding changes, so the bot can tell the formats apart. */
    public const VERSION = 'bk1';

    public const DIGEST_LENGTH = 16;

    /**
     * @param  array<string, mixed>  $document
     * @return array{token: string, digest: string, bytes: int}
     */
    public function execute(array $document): array
    {
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $digest = substr(hash('sha256', $json), 0, self::DIGEST_LENGTH);
        $payload = self::base64url(gzdeflate($json, 9));

        $token = self::VERSION.'.'.$payload.'.'.$digest;

        return [
            'token' => $token,
            'digest' => $digest,
            'bytes' => strlen($token),
        ];
    }

    /**
     * The inverse, for tests and for anyone checking what they're about to
     * submit. Returns null rather than throwing: a bad token is expected input,
     * not an exceptional condition.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return null;
        }

        [, $payload, $digest] = $parts;

        $binary = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($binary === false) {
            return null;
        }

        $json = @gzinflate($binary);

        if ($json === false || ! hash_equals($digest, substr(hash('sha256', $json), 0, self::DIGEST_LENGTH))) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
