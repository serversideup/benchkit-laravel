/** One level of a route's sweep. */
export interface CurvePoint {
    concurrency: number
    requests_per_second: number
    p50_ms?: number | null
    p95_ms?: number | null
    success_rate?: number | null
}

/** How much the route could take, measured closed-loop at rising concurrency. */
export interface HttpThroughput {
    requests_per_second: number
    /** Where the peak happened. */
    concurrency: number
    /** The cheapest level within a few percent of it — what the latency pass held open. */
    knee_concurrency?: number | null
    success_rate?: number
    total_requests?: number
    /** Wall time the load generator observed, against the window the sweep asked for. */
    elapsed_seconds?: number | null
    /** False means the sweep never saw it flatten, so this is a floor. */
    saturated?: boolean | null
    status_codes?: Record<string, number>
}

/**
 * What a visitor waits, measured open-loop at a rate below the peak.
 *
 * A separate pass on purpose: percentiles taken while a server is saturated
 * describe the queue in front of it, not the server.
 */
export interface HttpLatency {
    achieved_rps?: number | null
    p50_ms?: number | null
    p90_ms?: number | null
    p95_ms?: number | null
    p99_ms?: number | null
    success_rate?: number
    total_requests?: number
    elapsed_seconds?: number | null
    /** Always true on a published run; the validator rejects anything else. */
    corrected?: boolean
}

export interface HttpRoute {
    path?: string
    throughput: HttpThroughput
    latency?: HttpLatency | null
    curve?: CurvePoint[] | null
    /** The lowest concurrency at which the route stopped answering correctly. */
    breaking_point?: number | null
    /** What it answered there. Absent on runs recorded before it was kept. */
    breaking?: HttpBreaking | null
}

export interface HttpBreaking {
    concurrency: number
    status_codes: Record<string, number>
    errors: Record<string, number>
    /** The generator's reason when the level could not be measured at all. */
    failure?: string | null
    success_rate?: number
    total_requests?: number
    /** On the DB route: why its query failed, as the app classified it, oldest first. */
    causes?: string[]
}

export interface PhpHeadline {
    milliseconds: number
    records?: number
    // Statements the operation ran, one record each. The four CRUD tiles may
    // only share a bar scale when this matches across them; runs from before
    // schema 3 measured read as a single query returning `records` rows and
    // carry no statement count at all.
    statements?: number
    // The spread behind the mean. A mean over a handful of iterations can be
    // moved by one stalled iteration, and without these there is no way to
    // tell that happened.
    best_ms?: number | null
    worst_ms?: number | null
    rstdev?: number | null
    iterations?: number | null
    revolutions?: number | null
}

export interface PhpSubject {
    benchmark: string
    subject: string
    mean_us: number
    best_us?: number | null
    worst_us?: number | null
    stdev_us?: number | null
    rstdev?: number | null
    revolutions?: number | null
    iterations?: number | null
}

/**
 * What the database guaranteed while the CRUD benchmarks ran. These settings
 * decide whether a commit waits for the disk, which moves write results by
 * orders of magnitude — without them a slow write number can't be told apart
 * from a slow disk. The filesystem is recorded because a database on tmpfs is
 * measuring RAM; the path is not, because it identifies the machine.
 */
export interface DatabaseSpecs {
    driver: string | null
    version: string | null
    filesystem: string | null
    durability: Record<string, string | null>
}

/**
 * Hosting cost as stored: a number, a currency, and one fixed period. Free
 * text can't be compared, and "req/s per dollar" is the only reason to record
 * a price at all. The currency is what the submitter is actually billed, and
 * is never converted: the gallery ranks value within one currency at a time.
 */
export interface RunCost {
    amount: number
    currency: string
    period: 'monthly'
}

/**
 * The flat summary fields on every stored run — enough to render a gallery
 * card, filter, and sort without opening the full run. Derived from `run` by
 * shared/submission/run-document.mjs and recomputed by the PR validator, so the
 * summary can't drift from the detail it summarizes.
 */
export interface RunIndex {
    github: string
    submitted_at: string
    verified: boolean
    /**
     * The issue this result was submitted in, when it came through the bot.
     * Absent on runs filed before it was recorded, and on any added by hand.
     */
    issue?: number | null
    run_id: string
    label: string | null
    provider: string
    php_variation: string | null
    php_version: string | null
    cpu_cores: number | null
    json_rps: number | null
    /** The concurrency the peak happened at — 462 req/s at 147 and at 20 are different machines. */
    json_concurrency: number | null
    /**
     * The median from the open-loop pass, not a percentile from the sweep.
     * The sweep's percentiles describe a queue, and the tail of a short window
     * swings several-fold between runs on a busy host while the median holds.
     * A column people sort by has to be stable.
     */
    json_p50_ms: number | null
    static_rps: number | null
    static_p50_ms: number | null
    db_read_rps: number | null
    db_read_p50_ms: number | null
    /**
     * False when the sweep never saw throughput flatten, so the figure is the
     * most BenchKit could ask for rather than the most the machine can serve.
     * Shown with a ≥ and left out of the ranked sort.
     */
    saturated: boolean | null
    /** SQLite and Postgres in one ranked column is a misleading comparison. */
    database_driver: string | null
    /**
     * Whether this measurement can be trusted alongside somebody else's — not
     * whether the host is fast. See docs/shared/run/clean.mjs.
     */
    clean_run: boolean | null
    /** The leading reason it is not clean, for a chip on the row. */
    clean_missing: string | null
    /**
     * Where the HTTP load came from: 'self' when the server drove its own load
     * (sharing CPU with what it measured), 'external' when a second machine
     * drove it, null when the run has no HTTP stage. The gallery partitions on
     * this and never draws the two populations on one axis.
     */
    load_mode?: 'self' | 'external' | null
    php_read_ms: number | null
    cost_amount: number | null
    cost_currency: string | null
}

/** What /api/results/index.json returns — every run, summary fields only. */
export interface ResultsIndex {
    schema_version: number
    count: number
    runs: RunIndex[]
}

/** What /api/results/<run id>.json returns — one run, in full. */
export interface RunEntry extends RunIndex {
    run: {
        schema_version: number
        id: string
        created_at: string
        meta: {
            label: string
            provider?: string | null
            plan?: string | null
            datacenter?: string | null
            cost?: RunCost | null
        }
        settings_preset?: string | null
        stages_completed?: string[]
        /**
         * SHA-256 over everything in `run` except `meta` and this block, stamped
         * by the bot when it accepts a submission and re-checked on every pull
         * request and every site build. An edit to any measurement breaks it;
         * `meta` sits outside so a maintainer can still fix a host name or cost.
         */
        integrity?: { algorithm: 'sha256', digest: string }
        environment: {
            build_version?: string | null
            server: {
                cpu_model: string
                cpu_cores: string | number
                cpu_frequency?: string
                os: string
                ram: string
            }
            php: {
                php_version: string
                php_variation: string
                php_server_api?: string
                octane?: boolean
                op_cache?: string | boolean
                memory_limit?: string
                // Performance-relevant php.ini values only, and never a path —
                // opcache.preload ships as opcache.preload_enabled.
                ini?: Record<string, string | number | boolean>
                /**
                 * How the application was served. server/mode/workers are
                 * normalized so an FPM pool size and a FrankenPHP thread count
                 * can sit in the same column; workers_source names what the
                 * number counts so they are never silently equated. `settings`
                 * is whatever that particular server exposes, rendered as
                 * label/value — which is what lets an unfamiliar runtime show
                 * up at all rather than showing up as blanks.
                 */
                runtime?: {
                    server?: string | null
                    mode?: 'worker' | 'process-per-request' | null
                    workers?: number | null
                    workers_source?: string | null
                    front_end?: string | null
                    front_end_version?: string | null
                    settings?: Record<string, string>
                }
            }
            /** Which process the `php` block describes — see /bench/env. */
            php_environment_source?: 'web' | 'cli' | null
            laravel: {
                environment: {
                    laravel_version: string
                    /** APP_DEBUG. A run measured with it on is a development configuration. */
                    debug_mode?: boolean | null
                    app_env?: string | null
                }
                drivers?: Record<string, unknown>
            }
            database?: DatabaseSpecs | null
        }
        benchmarks: {
            http?: {
                mode?: string
                duration_seconds?: number
                connections?: number
                io_ms?: number
                /** Concurrency ceiling, whatever this server calls it. */
                workers?: number | null
                /** Both container ports report mode "loopback"; this separates them. */
                tls?: boolean | null
                /** The load held more connections open than the server has workers. */
                oversubscribed?: boolean | null
                /** The I/O route reached the ceiling its worker count implies. */
                pool_limited?: boolean | null
                /** Where the load came from. Absent on runs that predate external mode — provably self-tests. */
                generator?: {
                    mode?: 'self' | 'external'
                    rtt_ms?: number | null
                    oha_version?: string | null
                } | null
                /** Throughput landed at the generator's own ceiling; the validator rejects these. */
                generator_bound?: boolean | null
                routes: {
                    static?: HttpRoute
                    json?: HttpRoute
                    db_read?: HttpRoute
                    io?: HttpRoute
                }
            } | null
            php?: {
                headline: {
                    create?: PhpHeadline
                    read?: PhpHeadline
                    update?: PhpHeadline
                    delete?: PhpHeadline
                }
                subjects?: PhpSubject[]
            } | null
            cfspeedtest?: {
                latency_ms?: number | null
                download_mbps?: number | null
                upload_mbps?: number | null
            } | null
            geekbench?: {
                single: number
                multi: number
                version?: number | string | null
                url?: string | null
            } | null
            disk?: Array<{
                bs: string
                speed_r?: number | null
                speed_w?: number | null
                speed_rw?: number | null
                speed_units?: string | null
            }> | null
        }
    }
}

// ---- Shared display helpers ----

/**
 * One place for the partition's naming, so the listing, the detail page, and
 * the submit preview cannot drift. 'self' is back-filled for http-bearing runs
 * without a load_mode — they predate external mode, which provably makes them
 * self-tests.
 */
export const LOAD_MODE_LABELS = {
    self: 'Self-tested',
    external: 'External load'
} as const

export function loadMode(entry: Pick<RunIndex, 'load_mode' | 'json_rps' | 'static_rps' | 'db_read_rps'>): 'self' | 'external' | null {
    if (entry.load_mode === 'external') return 'external'
    if (entry.load_mode === 'self') return 'self'

    return entry.json_rps != null || entry.static_rps != null || entry.db_read_rps != null ? 'self' : null
}

export interface PrimaryMetric {
    label: string
    rps: number
    /** Where the peak happened. */
    concurrency: number | null
    /** What one visitor waits at a rate below the peak. */
    p50_ms: number | null
    /** A floor rather than a maximum; render it with a ≥. */
    isFloor: boolean
}

/**
 * The headline route for a gallery card, read straight off the flat columns so
 * a listing never has to open the full run. JSON first, then static, then DB
 * read — the same priority the detail page uses.
 */
export function primaryMetric(entry: RunIndex): PrimaryMetric | null {
    const candidates: Array<[string, number | null, number | null]> = [
        ['JSON', entry.json_rps, entry.json_p50_ms],
        ['static', entry.static_rps, entry.static_p50_ms],
        ['DB read', entry.db_read_rps, entry.db_read_p50_ms]
    ]

    for (const [label, rps, p50_ms] of candidates) {
        if (rps != null) {
            return {
                label,
                rps,
                // Only JSON carries a concurrency column; the fallbacks are for
                // runs that measured nothing else, where it would be absent.
                concurrency: label === 'JSON' ? entry.json_concurrency : null,
                p50_ms,
                isFloor: entry.saturated === false
            }
        }
    }

    return null
}

/**
 * Requests per second per unit of monthly cost, in whatever currency the run
 * was billed in.
 *
 * Deliberately not converted to a common currency. Doing that needs an exchange
 * rate, and any rate we ship is a number that is wrong by an unknown amount and
 * gets more wrong every day nobody updates it — for a figure people would
 * screenshot. The gallery compares within a single currency instead, which
 * needs no rate and cannot go stale. Ratios from different currencies are not
 * comparable, so callers must scope by currency before ranking — and by
 * load_mode, because self-tested and externally-driven throughput are two
 * different measurements.
 */
export function valuePerCostUnit(entry: RunIndex): number | null {
    const rps = primaryMetric(entry)?.rps
    const cost = entry.cost_amount

    if (rps == null || cost == null || cost <= 0) return null

    return rps / cost
}

export function formatNumber(n: number | null | undefined): string {
    if (n == null) return '—'
    return Math.round(n).toLocaleString('en-US')
}

/**
 * "cores", not "vCPU": the count comes from what the OS reports, and BenchKit
 * runs on bare metal as readily as on a VPS, where calling them vCPUs is wrong.
 */
export function coresLabel(v: string | number): string {
    return `${v} cores`
}

export function ramLabel(ram: string): string {
    const mb = Number.parseFloat(ram)
    if (Number.isNaN(mb)) return ram
    return `${Math.round(mb / 1024)} GB`
}

export function opcacheOn(v: string | boolean | undefined): boolean {
    return v === true || v === '1' || v === 'on'
}

// fio speeds arrive in KB/s (YABS writes `fio --minimal` bandwidth raw, with
// `speed_units: "KBps"`); show GB/s once they cross ~1000 MB/s.
export function formatThroughput(kbps: number | null | undefined): string {
    if (kbps == null) return '—'
    const mbps = kbps / 1024
    return mbps >= 1000 ? `${(mbps / 1024).toFixed(2)} GB/s` : `${Math.round(mbps)} MB/s`
}

/**
 * "€20/mo" — the currency the submitter is billed, formatted properly rather
 * than given a dollar sign and hoped for. Never converts.
 */
export function monthlyCostLabel(amount: number | null | undefined, currency: string | null | undefined): string | null {
    if (amount == null || !currency) return null

    try {
        const formatted = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
            maximumFractionDigits: 2
        }).format(amount)

        return `${formatted}/mo`
    } catch {
        // Unknown code — show the number and the code rather than nothing.
        return `${amount} ${currency}/mo`
    }
}

export function costLabel(cost: RunCost | null | undefined): string | null {
    return monthlyCostLabel(cost?.amount, cost?.currency)
}
