/**
 * Whether each run-quality condition holds, with none of the words used to
 * describe it.
 *
 * Four surfaces say something about run quality and each phrases it for its own
 * reader. What they may not disagree on is whether a condition is true and what
 * number to recommend, so the predicates live here and the wording stays with
 * each surface.
 *
 * Imports nothing on purpose: this runs inside the app's Vite bundle, inside
 * Nuxt, and on a bare Node runner validating submissions from forks.
 */

/** Filesystems that are memory wearing a disk's name. */
export const MEMORY_FILESYSTEMS = ['tmpfs', 'ramfs', 'memory']

/**
 * Caches that are files on disk, so the command line and the web process agree
 * about them — unlike OPcache, which each SAPI holds separately.
 */
export const OPTIMIZABLE_CACHES = ['config', 'routes', 'events']

/** How much slower the loaded tail may be than the idle median before it is a queue. */
export const TAIL_GROWTH = 5

const routesOf = http => http?.routes ?? {}

/** A route whose sweep never flattened has measured a floor, not a maximum. */
export const unsaturatedRoutes = http => Object.entries(routesOf(http))
    .filter(([, route]) => route?.throughput?.saturated === false)
    .map(([key]) => key)

/** oha's success rate is transport-level; only the codes show a 503 storm. */
export const failingRoutes = http => Object.entries(routesOf(http))
    .filter(([, route]) => Object.keys(route?.throughput?.status_codes ?? {})
        .some(code => Number(code) < 200 || Number(code) >= 300))
    .map(([key]) => key)

const outside2xx = code => Number(code) < 200 || Number(code) >= 300

/**
 * What a route answered at the level it broke, reduced to the one failure that
 * accounts for most of it: an HTTP status outside 2xx, a transport error that
 * came back before any status, or the generator's own reason for measuring
 * nothing. `share` is the fraction of the level's requests it covers.
 *
 * Null for runs recorded before the level's answers were kept, and for a level
 * whose counts are missing, so the wording can fall back to the number alone.
 */
export const breakingAnswer = (breaking) => {
    if (!breaking) return null
    if (breaking.failure) return { kind: 'unmeasured', failure: breaking.failure, share: 1 }

    const statuses = Object.entries(breaking.status_codes ?? {})
    const errors = Object.entries(breaking.errors ?? {})
    const total = [...statuses, ...errors].reduce((sum, [, count]) => sum + count, 0)

    const [worst] = [
        ...statuses.filter(([code]) => outside2xx(code)).map(([code, count]) => ({ kind: 'status', code: Number(code), count })),
        ...errors.map(([error, count]) => ({ kind: 'transport', error, count })),
    ].sort((a, b) => b.count - a.count)

    return worst && total > 0 ? { ...worst, share: worst.count / total } : null
}

/**
 * Routes that stopped answering correctly partway up the sweep, with the level
 * they gave out at and what they answered there.
 *
 * Different from a route that flattened: this one had more to give and
 * something else refused. The answer says what; the concurrency is the number
 * to go looking with.
 */
export const brokenRoutes = http => Object.entries(routesOf(http))
    .filter(([, route]) => (route?.breaking?.concurrency ?? route?.breaking_point) != null)
    .map(([key, route]) => ({
        key,
        at: route.breaking?.concurrency ?? route.breaking_point,
        answer: breakingAnswer(route.breaking),
    }))
    .sort((a, b) => a.at - b.at)

export const generatorBound = http => http?.generator_bound === true

export const selfTested = http => (http?.generator?.mode ?? 'self') !== 'external'

export const debugMode = environment => environment?.laravel?.environment?.debug_mode === true

export const opcacheOff = environment => environment?.php?.op_cache != null
    && String(environment.php.op_cache) !== '1'

export const notOptimized = (environment) => {
    const cache = environment?.laravel?.cache ?? {}

    return OPTIMIZABLE_CACHES.some(key => cache[key] === false)
        || String(environment?.php?.ini?.['opcache.validate_timestamps'] ?? '0') === '1'
}

export const memoryDatabase = environment => MEMORY_FILESYSTEMS
    .includes(String(environment?.database?.filesystem ?? '').toLowerCase())

export const unsafeWrites = environment => Object
    .values(environment?.database?.durability ?? {})
    .some(value => ['off', '0'].includes(String(value).toLowerCase()))

const asInteger = (value) => {
    const parsed = Number.parseInt(String(value ?? ''), 10)

    return Number.isFinite(parsed) ? parsed : null
}

/** A worker is a process. Measured on this project's own image, one resides in roughly this much. */
const WORKER_MB = 40

/** Left for the OS, the database, and BenchKit itself. */
const MEMORY_RESERVE = 0.25

/**
 * Cores this run could never have used, and what the pool should be instead.
 *
 * Only meaningful for a server that ties a request to a worker for its
 * duration; a worker-mode runtime multiplexes, so the comparison does not hold.
 *
 * The suggestion is bounded by memory rather than by cores, because a worker is
 * a process and a request spends much of its life waiting rather than
 * computing — which is what /bench/io exists to show. A core-count pool leaves
 * the machine idle under any load with I/O in it. Falls back to the core count
 * when memory could not be read, which is the conservative direction.
 *
 * @returns {{cores: number, workers: number, idleCores: number, suggested: number, memoryBound: boolean}|null}
 */
export const undersizedPool = (environment, http) => {
    const cores = asInteger(environment?.server?.cpu_cores)
    const workers = asInteger(http?.workers)
    const perRequest = environment?.php?.runtime?.mode === 'process-per-request'

    if (!perRequest || !cores || !workers || workers >= cores) {
        return null
    }

    const ramMb = Number.parseFloat(String(environment?.server?.ram ?? '')) || null
    const fromMemory = ramMb ? Math.floor((ramMb * (1 - MEMORY_RESERVE)) / WORKER_MB) : null
    const suggested = Math.max(cores, fromMemory ?? cores)

    return { cores, workers, idleCores: cores - workers, suggested, memoryBound: suggested > cores }
}

/** What one worker is assumed to cost, for copy that has to explain the suggestion. */
export const workerFootprintMb = () => WORKER_MB

/**
 * Routes whose tail grows once the server is busy, worst first.
 *
 * Measured against the idle median rather than the idle tail: the tail of one
 * short window is a couple of requests and moves wildly, which silently stopped
 * this firing.
 *
 * @returns {{key: string, idle: number, loaded: number, ratio: number}[]}
 */
export const tailUnderLoad = http => Object.entries(routesOf(http))
    .map(([key, route]) => {
        const idle = (route?.curve ?? []).find(point => point.concurrency === 1)?.p50_ms ?? null
        const loaded = route?.latency?.p95_ms ?? null

        return { key, idle, loaded, ratio: idle && loaded ? loaded / idle : null }
    })
    .filter(({ ratio }) => ratio !== null && ratio > TAIL_GROWTH)
    .sort((a, b) => b.ratio - a.ratio)

/**
 * How much of a round trip was jitter rather than distance, measured against an
 * idle server before the run started.
 *
 * Reported beside a growing tail rather than used to suppress it. Suppressing
 * on this was tried and was wrong: between two runs the handshake wobble fell
 * fourfold while the tail did not move at all.
 */
export const pathJitterMs = (http) => {
    const best = http?.generator?.rtt_ms
    const worst = http?.generator?.rtt_worst_ms

    return best == null || worst == null ? null : Math.max(0, worst - best)
}

/** Above this share of connections carrying a request, the generator kept up. */
export const CONNECTIONS_BUSY = 0.85

/** Above this share of a request spent on the wire, the path is what bounds the run. */
export const NETWORK_SHARE = 0.5

/**
 * What stopped the sweep climbing, for a run that never found its limit.
 *
 * Falls back to 'ceiling' for a run recorded before reach existed, which is
 * correct: the figure those runs quoted for the distance case was wrong.
 *
 * @returns {'distant'|'descriptors'|'idle'|'ceiling'}
 */
export const unsaturatedCause = (http) => {
    const reach = http?.reach ?? null

    if (!reach?.connections || reach.busy_connections == null) {
        return 'ceiling'
    }

    if (reach.busy_connections / reach.connections < CONNECTIONS_BUSY) {
        return generatorBound(http) ? 'ceiling' : 'idle'
    }

    if (reach.capped_by === 'generator') {
        return 'descriptors'
    }

    return (reach.network_share ?? 0) >= NETWORK_SHARE ? 'distant' : 'ceiling'
}
