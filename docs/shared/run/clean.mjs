/**
 * Whether a run's numbers can be trusted alongside somebody else's.
 *
 * This asks about the measurement, never about the machine. A modest VPS
 * measured carefully passes; a fast one measured through a laptop on hotel
 * wifi does not. Naming matters here — anything like "great result" gets read
 * as "fast server", and a gallery that badges hosts by luck of measurement is
 * worse than one with no badge at all. "Clean" says uncontaminated without
 * saying fast, and it is the word the reasons below are the opposite of.
 *
 * Deliberately binary. Tiers would imply a precision these checks do not have,
 * and would invite tuning the run to the badge rather than tuning the server.
 *
 * Every check below already exists somewhere in the app as a caveat; this
 * gathers them into one question so the app and the gallery cannot disagree
 * about the answer.
 */

/** A route whose sweep never flattened has measured a floor, not a maximum. */
const unsaturated = (routes) => Object.entries(routes)
    .filter(([, route]) => route?.throughput?.saturated === false)
    .map(([key]) => key)

/** oha's success rate is transport-level; only the codes show a 503 storm. */
const failing = (routes) => Object.entries(routes)
    .filter(([, route]) => Object.keys(route?.throughput?.status_codes ?? {})
        .some((code) => Number(code) < 200 || Number(code) >= 300))
    .map(([key]) => key)

/** Filesystems that are memory wearing a disk's name. */
const MEMORY_FILESYSTEMS = ['tmpfs', 'ramfs', 'memory']

/**
 * @returns {{ok: boolean, reasons: string[]}} Short reasons, phrased as what
 *   is missing, so a listing can show why a run fell short without opening it.
 */
export const cleanRun = (run) => {
    const http = run?.benchmarks?.http ?? null
    const environment = run?.environment ?? {}
    const routes = http?.routes ?? {}
    const reasons = []

    if (!http || Object.keys(routes).length === 0) {
        return { ok: false, reasons: ['no load test'] }
    }

    // A self-test shares the machine's CPU with the thing it measures, so its
    // throughput is a floor by construction however carefully it was run.
    if ((http.generator?.mode ?? 'self') !== 'external') {
        reasons.push('self-tested')
    }

    if (http.generator_bound === true) {
        reasons.push('generator was the limit')
    }

    if (unsaturated(routes).length > 0) {
        reasons.push('not saturated')
    }

    if (failing(routes).length > 0) {
        reasons.push('requests failed')
    }

    // Config and route caches are files on disk, so the command line and the
    // web process agree about them — unlike OPcache, which each SAPI holds
    // separately.
    const cache = environment.laravel?.cache ?? {}
    const uncached = ['config', 'routes', 'events'].filter((key) => cache[key] === false)
    const revalidating = String(environment.php?.ini?.['opcache.validate_timestamps'] ?? '0') === '1'

    if (uncached.length > 0 || revalidating) {
        reasons.push('app not optimized')
    }

    if (environment.php?.op_cache != null && String(environment.php.op_cache) !== '1') {
        reasons.push('OPcache off')
    }

    if (environment.laravel?.environment?.debug_mode === true) {
        reasons.push('debug mode on')
    }

    if (MEMORY_FILESYSTEMS.includes(String(environment.database?.filesystem ?? '').toLowerCase())) {
        reasons.push('database in memory')
    }

    return { ok: reasons.length === 0, reasons }
}
