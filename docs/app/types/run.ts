export interface HttpRoute {
    path?: string
    requests_per_second: number
    success_rate: number
    p50_ms: number
    p95_ms: number
    p99_ms: number
    total_requests?: number
    /** Wall time the load generator observed, against the requested duration. */
    elapsed_seconds?: number | null
    status_codes?: Record<string, number>
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
    json_p95_ms: number | null
    static_rps: number | null
    static_p95_ms: number | null
    db_read_rps: number | null
    db_read_p95_ms: number | null
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
            }> | null
        }
    }
}

// ---- Shared display helpers ----

export interface PrimaryMetric {
    label: string
    rps: number
    p95_ms: number | null
}

/**
 * The headline route for a gallery card, read straight off the flat columns so
 * a listing never has to open the full run. JSON first, then static, then DB
 * read — the same priority the detail page uses.
 */
export function primaryMetric(entry: RunIndex): PrimaryMetric | null {
    const candidates: Array<[string, number | null, number | null]> = [
        ['JSON', entry.json_rps, entry.json_p95_ms],
        ['static', entry.static_rps, entry.static_p95_ms],
        ['DB read', entry.db_read_rps, entry.db_read_p95_ms]
    ]

    for (const [label, rps, p95_ms] of candidates) {
        if (rps != null) return { label, rps, p95_ms }
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
 * comparable, so callers must scope by currency before ranking.
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

// fio speeds arrive in MB/s; show GB/s once they cross ~1000.
export function formatThroughput(mbps: number | null | undefined): string {
    if (mbps == null) return '—'
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
