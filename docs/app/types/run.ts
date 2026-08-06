import { approximateMonthlyUsd } from '~/utils/fx'

export interface HttpRoute {
    path?: string
    requests_per_second: number
    success_rate: number
    p50_ms: number
    p95_ms: number
    p99_ms: number
    total_requests?: number
}

export interface PhpHeadline {
    milliseconds: number
    records?: number
    label?: string
}

/**
 * Hosting cost as stored: a number, a currency, and one fixed period. Free
 * text can't be compared, and "req/s per dollar" is the only reason to record
 * a price at all. The currency is what the submitter is actually billed —
 * conversion happens at render (utils/fx.ts), never in the file.
 */
export interface RunCost {
    amount: number
    currency: string
    period: 'monthly'
}

/**
 * The flat summary fields on every stored run — enough to render a gallery
 * card, filter, and sort without opening the full run. Derived from `run` by
 * .github/scripts/run-document.mjs and recomputed by the PR validator, so the
 * summary can't drift from the detail it summarizes.
 */
export interface RunIndex {
    github: string
    submitted_at: string
    verified: boolean
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
        environment: {
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
                octane?: boolean
                op_cache?: string | boolean
                memory_limit?: string
            }
            laravel: {
                environment: { laravel_version: string }
                drivers?: Record<string, unknown>
            }
        }
        benchmarks: {
            http?: {
                mode?: string
                duration_seconds?: number
                connections?: number
                io_ms?: number
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
            } | null
            cfspeedtest?: {
                colo?: string | null
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
 * Requests per second per USD per month — the number the gallery exists to
 * make visible. Approximate by construction: costs are billed in different
 * currencies and converted with a hand-maintained table, so the UI says so.
 */
export function valuePerUsd(entry: RunIndex): number | null {
    const rps = primaryMetric(entry)?.rps
    const usd = approximateMonthlyUsd(entry.cost_amount, entry.cost_currency)

    if (rps == null || usd == null || usd <= 0) return null

    return rps / usd
}

export function formatNumber(n: number | null | undefined): string {
    if (n == null) return '—'
    return Math.round(n).toLocaleString('en-US')
}

export function coresLabel(v: string | number): string {
    return `${v} vCPU`
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
