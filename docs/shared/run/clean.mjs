/**
 * Whether a run's numbers can be trusted alongside somebody else's.
 *
 * This asks about the measurement, never about the machine. A modest VPS
 * measured carefully passes; a fast one measured through a laptop on hotel wifi
 * does not. Naming matters here — anything like "great result" gets read as
 * "fast server", and a gallery that badges hosts by luck of measurement is
 * worse than one with no badge at all. "Clean" says uncontaminated without
 * saying fast, and it is the word the reasons below are the opposite of.
 *
 * Binary rather than tiered: tiers would imply a precision these checks do not
 * have, and would invite tuning the run to the badge rather than the server.
 */

import {
    debugMode,
    failingRoutes,
    generatorBound,
    memoryDatabase,
    notOptimized,
    opcacheOff,
    selfTested,
    unsaturatedRoutes
} from './conditions.mjs'

/**
 * @returns {{ok: boolean, reasons: string[]}} Short reasons, phrased as what
 *   is missing, so a listing can show why a run fell short without opening it.
 */
export const cleanRun = (run) => {
    const http = run?.benchmarks?.http ?? null
    const environment = run?.environment ?? {}
    const reasons = []

    if (!http || Object.keys(http.routes ?? {}).length === 0) {
        return { ok: false, reasons: ['no load test'] }
    }

    // A self-test shares the machine's CPU with the thing it measures, so its
    // throughput is a floor by construction however carefully it was run.
    if (selfTested(http)) {
        reasons.push('self-tested')
    }

    if (generatorBound(http)) {
        reasons.push('generator was the limit')
    }

    if (unsaturatedRoutes(http).length > 0) {
        reasons.push('not saturated')
    }

    if (failingRoutes(http).length > 0) {
        reasons.push('requests failed')
    }

    if (notOptimized(environment)) {
        reasons.push('app not optimized')
    }

    if (opcacheOff(environment)) {
        reasons.push('OPcache off')
    }

    if (debugMode(environment)) {
        reasons.push('debug mode on')
    }

    if (memoryDatabase(environment)) {
        reasons.push('database in memory')
    }

    return { ok: reasons.length === 0, reasons }
}
