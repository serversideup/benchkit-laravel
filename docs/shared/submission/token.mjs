// Reading a BenchKit submission token.
//
//     bk1.<base64url(deflate-raw(json))>.<sha256(json)[0:16]>
//
// The app writes these (app/Actions/Runs/EncodeSubmissionToken.php); the
// submission bot and the /results/submit page read them. Everything here uses
// only web platform APIs — DecompressionStream and crypto.subtle — so one
// implementation serves both a GitHub Actions runner and a browser tab.
//
// Why a token at all: a submission used to travel as pretty-printed JSON in a
// pre-filled issue URL, and GitHub cut the request URI at around 6-8 KB. A
// Quick run already produced a 7,121-character URL, so results were arriving
// truncated mid-number with nothing to notice it. Deflate shrinks the document
// roughly 5x and base64url costs nothing to percent-encode, which puts even a
// worst-case Full run near 2,800 characters.
//
// The digest is tamper-*evidence*, not authenticity. BenchKit is open source
// and self-hosted, so anyone willing to run the encoder can mint whatever they
// like. What it does rule out is the whole class of low-effort damage — a
// truncated URL, a mangled paste, a number hand-edited in the issue body — and
// it lets the bot say "this was modified after the app generated it" rather
// than "invalid JSON".

export const TOKEN_VERSION = 'bk1'
export const DIGEST_LENGTH = 16

/** A GitHub issue body maxes out around 65 KB; anything past this is not a submission. */
export const MAX_TOKEN_LENGTH = 100_000

/**
 * Deflate can expand roughly 1000:1, so a small token could otherwise ask the
 * runner to materialise gigabytes. A real document is a few kilobytes.
 */
export const MAX_PAYLOAD_BYTES = 512 * 1024

export const TOKEN_RE = new RegExp(`${TOKEN_VERSION}\\.[A-Za-z0-9_-]+\\.[0-9a-f]{${DIGEST_LENGTH}}`)

/** The token in a block of text (an issue body, a paste), or null. */
export const findToken = text => (typeof text === 'string' ? text.match(TOKEN_RE)?.[0] ?? null : null)

/**
 * True when the text holds something that was meant to be a token but doesn't
 * match — almost always one that was cut short or copied in part. Worth telling
 * apart from "no token here": the first is a submitter to help, the second is
 * an issue to ignore.
 */
export const looksLikeToken = text => typeof text === 'string' && text.includes(`${TOKEN_VERSION}.`) && !findToken(text)

export const sha256Hex = async (bytes) => {
    const digest = await crypto.subtle.digest('SHA-256', bytes)

    return [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('')
}

const base64urlToBytes = (payload) => {
    const binary = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))

    return Uint8Array.from(binary, character => character.charCodeAt(0))
}

/**
 * Inflate with a ceiling. DecompressionStream has no size limit of its own, so
 * the cap has to be applied while reading rather than after.
 */
const inflateRaw = async (bytes) => {
    const stream = new Response(bytes).body.pipeThrough(new DecompressionStream('deflate-raw'))
    const reader = stream.getReader()
    const chunks = []
    let size = 0

    for (;;) {
        const { done, value } = await reader.read()

        if (done) break

        size += value.length

        if (size > MAX_PAYLOAD_BYTES) {
            await reader.cancel()
            throw new Error(`The submission expands to more than ${MAX_PAYLOAD_BYTES} bytes, which is far larger than any real run.`)
        }

        chunks.push(value)
    }

    const out = new Uint8Array(size)
    let at = 0

    for (const chunk of chunks) {
        out.set(chunk, at)
        at += chunk.length
    }

    return out
}

/**
 * Decode a token into the submission document it carries.
 *
 * Throws with a message written to be shown to the submitter — every failure
 * here is expected input, not a bug, and the difference between "your token was
 * cut short" and "your token was edited" is worth telling them.
 *
 * @returns {Promise<object>} the submission document
 */
export const decodeToken = async (token) => {
    const trimmed = String(token ?? '').trim()

    if (trimmed.length > MAX_TOKEN_LENGTH) {
        throw new Error('That token is too long to be a BenchKit submission.')
    }

    const parts = trimmed.split('.')

    if (parts.length !== 3 || parts[0] !== TOKEN_VERSION) {
        throw new Error(`This doesn't look like a submission token — expected one starting with "${TOKEN_VERSION}." and ending in a 16-character checksum.`)
    }

    const [, payload, digest] = parts

    let bytes

    try {
        bytes = await inflateRaw(base64urlToBytes(payload))
    } catch (error) {
        // A truncated token is the common case: the deflate stream simply runs
        // out, which is exactly the failure the old JSON-in-a-URL format could
        // not detect. DecompressionStream's own error is often empty, so don't
        // rely on it carrying anything readable.
        const detail = error.message || error.name || 'the compressed data ended unexpectedly'

        throw new Error(`The token could not be unpacked, which usually means it was cut short or only partly copied: ${detail}.`)
    }

    const hash = await sha256Hex(bytes)

    if (hash.slice(0, DIGEST_LENGTH) !== digest) {
        throw new Error('The token\'s checksum does not match its contents, so it was altered after the app generated it. Re-copy it from the app and try again.')
    }

    let document

    try {
        document = JSON.parse(new TextDecoder().decode(bytes))
    } catch (error) {
        throw new Error(`The token unpacked but did not contain valid JSON: ${error.message}`)
    }

    if (document === null || typeof document !== 'object' || Array.isArray(document)) {
        throw new Error('The token did not contain a run document.')
    }

    return document
}
