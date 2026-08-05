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

export interface RunEntry {
    submission: {
        github: string
        submitted_at: string
        verified: boolean
    }
    run: {
        schema_version: number
        id: string
        created_at: string
        meta: {
            label: string
            provider?: string | null
            plan?: string | null
            datacenter?: string | null
            cost?: number | string | null
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

export function primaryRoute(entry: RunEntry): HttpRoute | undefined {
    const r = entry.run.benchmarks.http?.routes
    return r?.json ?? r?.static ?? r?.db_read ?? r?.io
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

export function costLabel(cost: number | string | null | undefined): string | null {
    if (cost == null || cost === '') return null
    const n = typeof cost === 'string' ? Number.parseFloat(cost) : cost
    if (Number.isNaN(n)) return String(cost)
    return `$${n}/mo`
}
