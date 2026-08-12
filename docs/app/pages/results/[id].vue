<template>
    <UContainer class="mx-auto max-w-[960px] py-10">
        <UButton
            to="/results"
            variant="ghost"
            color="neutral"
            size="sm"
            icon="i-lucide-arrow-left"
            class="mb-6"
        >
            All results
        </UButton>

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl sm:text-3xl font-bold text-[#F7F7F7]">
                        {{ run.meta.label }}
                    </h1>
                    <UBadge
                        v-if="submitter.verified"
                        color="primary"
                        variant="subtle"
                    >
                        <UIcon
                            name="i-lucide-badge-check"
                            class="size-3.5 mr-1"
                        />Maintainer run
                    </UBadge>
                    <UBadge
                        v-else
                        color="neutral"
                        variant="subtle"
                    >
                        Unverified
                    </UBadge>
                </div>
                <div class="mt-2 flex items-center gap-2 text-sm text-[#94979C]">
                    <template v-if="submitter.github">
                        <img
                            :src="`https://github.com/${submitter.github}.png?size=40`"
                            :alt="submitter.github"
                            class="size-5 rounded-full"
                            loading="lazy"
                        >
                        <a
                            :href="`https://github.com/${submitter.github}`"
                            target="_blank"
                            class="hover:text-[#F7F7F7]"
                        >@{{ submitter.github }}</a>
                        <span class="text-[#61656C]">·</span>
                    </template>
                    <span>{{ submitter.submitted_at }}</span>
                    <span class="text-[#61656C]">·</span>
                    <a
                        :href="`https://github.com/serversideup/benchkit-laravel/blob/main/${sourcePath}`"
                        target="_blank"
                        class="inline-flex items-center gap-1 hover:text-[#F7F7F7]"
                    >
                        <UIcon
                            name="i-lucide-file-json"
                            class="size-3.5"
                        /> Source
                    </a>
                </div>
            </div>
        </div>

        <!-- At-a-glance spec strip: whose machine, running what -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div
                v-for="spec in specs"
                :key="spec.label"
                class="rounded-xl border border-[#22262F] bg-[#0C0E12] p-4"
            >
                <div class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-[#61656C]">
                    <UIcon
                        :name="spec.icon"
                        class="size-3.5"
                    /> {{ spec.label }}
                </div>
                <div class="mt-2 text-lg font-semibold text-[#F7F7F7] leading-tight break-words">
                    {{ spec.value }}
                </div>
                <div
                    v-if="spec.sub"
                    class="text-xs text-[#94979C] mt-0.5 break-words"
                >
                    {{ spec.sub }}
                </div>
            </div>
        </div>

        <!-- Panels card -->
        <div class="rounded-2xl border border-[#22262F] bg-[#0C0E12] px-6 sm:px-8">
            <!-- HTTP throughput -->
            <ResultsPanel
                v-if="routes.length"
                title="Web server load test"
            >
                <template #aside>
                    <!-- Octane is a property of the PHP runtime, not of the
                         HTTP benchmark — reading it off `http` here silently
                         labelled every worker-mode run "classic mode". -->
                    <ResultsChip>{{ run.environment.php.octane ? 'worker mode' : 'classic mode' }}</ResultsChip>
                    <ResultsChip v-if="http.duration_seconds">
                        {{ http.duration_seconds }}s
                    </ResultsChip>
                    <ResultsChip v-if="http.connections">
                        {{ http.connections }} connections
                    </ResultsChip>
                    <ResultsChip v-if="http.io_ms != null">
                        I/O {{ http.io_ms }}ms
                    </ResultsChip>
                    <ResultsChip v-if="http.mode">
                        {{ http.mode }}
                    </ResultsChip>
                </template>

                <p class="mt-2 text-xs text-[#61656C]">
                    Saturation test — a fixed connection count held open, reporting max throughput. Tail-latency
                    percentiles are indicative.
                </p>

                <!-- Mobile: one route per block -->
                <div class="mt-5 flex flex-col divide-y divide-[#22262F] md:hidden">
                    <div
                        v-for="benchRoute in routes"
                        :key="`m-${benchRoute.key}`"
                        class="py-5 first:pt-0 last:pb-0"
                    >
                        <p class="text-sm font-medium text-[#CECFD2]">
                            {{ benchRoute.label }}
                        </p>
                        <p class="text-xs text-[#94979C] mt-0.5">
                            {{ benchRoute.description }}
                        </p>
                        <div class="mt-2">
                            <p class="text-5xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">
                                {{ round(benchRoute.data.requests_per_second) }}
                            </p>
                            <p class="mt-1.5 text-sm text-[#94979C] font-mono">
                                req/s
                            </p>
                        </div>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <div
                                v-for="p in PERCENTILES"
                                :key="`m-${benchRoute.key}-${p.key}`"
                                class="flex items-center gap-2"
                            >
                                <span class="w-24 shrink-0 text-xs text-[#94979C]">{{ p.human }} <span class="text-[#61656C]">{{ p.key }}</span></span>
                                <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums text-[#CECFD2]">{{ benchRoute.values[p.key] }}ms</span>
                                <ResultsBar
                                    class="flex-1 h-2"
                                    :percent="benchRoute.widths[p.key]"
                                    :color="p.color"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop: routes across, percentiles down -->
                <div
                    class="mt-7 hidden md:grid gap-x-8 gap-y-3 items-center"
                    :style="`grid-template-columns: 96px repeat(${routes.length}, minmax(0, 1fr))`"
                >
                    <div class="self-end" />
                    <div
                        v-for="benchRoute in routes"
                        :key="`head-${benchRoute.key}`"
                        class="self-stretch flex flex-col"
                    >
                        <p class="text-sm font-medium text-[#CECFD2]">
                            {{ benchRoute.label }}
                        </p>
                        <p class="text-xs text-[#94979C] mt-0.5">
                            {{ benchRoute.description }}
                        </p>
                        <div class="mt-auto pt-4">
                            <p class="text-4xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">
                                {{ round(benchRoute.data.requests_per_second) }}
                            </p>
                            <p class="mt-1.5 text-sm text-[#94979C] font-mono">
                                req/s
                            </p>
                        </div>
                    </div>

                    <template
                        v-for="p in PERCENTILES"
                        :key="p.key"
                    >
                        <span class="text-xs text-[#94979C]">{{ p.human }} <span class="text-[#61656C]">{{ p.key }}</span></span>
                        <div
                            v-for="benchRoute in routes"
                            :key="`${benchRoute.key}-${p.key}`"
                            class="flex items-center gap-2"
                        >
                            <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums text-[#CECFD2]">{{ benchRoute.values[p.key] }}ms</span>
                            <ResultsBar
                                class="flex-1 h-2"
                                :percent="benchRoute.widths[p.key]"
                                :color="p.color"
                            />
                        </div>
                    </template>
                </div>
            </ResultsPanel>

            <!-- PHP / database -->
            <ResultsPanel
                v-if="phpOps.length"
                title="Laravel database performance"
            >
                <template #aside>
                    <ResultsChip>{{ phpOps[0]?.records }} records per operation</ResultsChip>
                </template>

                <p
                    v-if="phpOps[0]?.comparable"
                    class="mt-2 text-xs text-[#94979C]"
                >
                    ↓ Lower is better — time to run {{ phpOps[0]?.records }} statements, one record each
                </p>
                <p
                    v-else
                    class="mt-2 text-xs text-[#94979C]"
                >
                    Recorded before these four were measured the same way — read was a single query returning
                    {{ phpOps[0]?.records }} rows rather than {{ phpOps[0]?.records }} separate reads, so it is not
                    comparable with the other three and they share no scale here.
                </p>

                <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-x-6 sm:gap-x-10 gap-y-6">
                    <div
                        v-for="op in phpOps"
                        :key="op.key"
                    >
                        <p class="flex items-center gap-1.5">
                            <img
                                :src="`/images/results/${op.key}.png`"
                                :alt="op.label"
                                class="w-4 h-4"
                            >
                            <span class="text-sm font-medium text-[#94979C]">{{ op.label }}</span>
                        </p>
                        <p class="mt-1.5 text-4xl text-[#F7F7F7] font-mono font-medium leading-none">
                            {{ formatMs(op.milliseconds) }}
                        </p>
                        <ResultsBar
                            v-if="op.percent !== null"
                            class="mt-3.5 h-2"
                            :percent="op.percent"
                        />
                        <p class="mt-2 text-xs text-[#61656C] leading-snug">
                            {{ op.detail }}
                        </p>
                        <!-- A mean is only worth its digits if the iterations
                             behind it agreed. When they didn't, say so on the
                             number rather than in a footnote nobody reads. -->
                        <p
                            v-if="op.rstdev !== null"
                            class="mt-1 text-xs font-mono leading-snug"
                            :class="isHighVariance(op.rstdev) ? 'text-[#F79009]' : 'text-[#61656C]'"
                        >
                            ±{{ op.rstdev }}%<template v-if="op.iterations">
                                over {{ op.iterations }} runs
                            </template>
                            <template v-if="isHighVariance(op.rstdev)">
                                — unstable
                            </template>
                        </p>
                    </div>
                </div>
            </ResultsPanel>

            <!-- Network -->
            <ResultsPanel
                v-if="cfspeedtest"
                title="Network speed test"
            >
                <p class="mt-2 text-sm text-[#94979C]">
                    Measured from the server to Cloudflare's nearest edge — this latency rides on every external request the app makes.
                </p>

                <div class="mt-6 flex flex-wrap items-end gap-x-10 gap-y-6">
                    <div
                        v-for="stat in networkStats"
                        :key="stat.label"
                    >
                        <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                            <img
                                :src="stat.icon"
                                alt=""
                                class="w-3.5"
                            > {{ stat.label }}
                        </p>
                        <p class="flex items-baseline gap-2 mt-1">
                            <span class="text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ stat.value }}</span>
                            <span class="text-sm text-[#94979C] font-mono">{{ stat.unit }}</span>
                        </p>
                    </div>
                </div>
            </ResultsPanel>

            <!-- Hardware: Geekbench + disk throughput -->
            <ResultsPanel
                v-if="geekbench || diskRows.length"
                title="Hardware"
            >
                <template
                    v-if="geekbench?.url"
                    #aside
                >
                    <a
                        :href="geekbench.url"
                        target="_blank"
                        class="text-xs font-mono text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]"
                    >View on Geekbench &rarr;</a>
                </template>

                <p class="mt-2 text-xs text-[#94979C]">
                    ↑ Higher is better
                </p>

                <div
                    v-if="geekbench"
                    class="mt-6 flex flex-wrap items-end gap-x-12 gap-y-6"
                >
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                            <img
                                src="/images/results/single-core.png"
                                alt=""
                                class="w-3.5"
                            > Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} single-core
                        </p>
                        <p class="mt-1 text-4xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">
                            {{ round(geekbench.single) }}
                        </p>
                    </div>
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                            <img
                                src="/images/results/multi-core.png"
                                alt=""
                                class="w-3.5"
                            > Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} multi-core
                        </p>
                        <p class="mt-1 text-4xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">
                            {{ round(geekbench.multi) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="diskRows.length"
                    class="mt-7 flex flex-col gap-5"
                >
                    <div
                        v-for="row in diskRows"
                        :key="row.bs"
                    >
                        <p class="text-xs text-[#CECFD2] font-mono mb-2">
                            Disk I/O <span class="text-[#61656C]">&middot; {{ row.bs }}</span>
                        </p>
                        <div class="flex flex-col gap-2.5">
                            <div
                                v-for="col in DISK_COLS"
                                :key="`${row.bs}-${col.key}`"
                                class="flex items-center gap-3"
                            >
                                <span class="w-16 shrink-0 text-xs text-[#94979C]">{{ col.label }}</span>
                                <ResultsBar
                                    class="flex-1 h-2"
                                    :percent="row.widths[col.key]"
                                />
                                <span class="w-[84px] shrink-0 text-right text-xs text-[#CECFD2] font-mono tabular-nums">{{ formatThroughput(row.values[col.key]) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </ResultsPanel>

            <!-- Environment -->
            <ResultsPanel title="Environment">
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-3 text-sm">
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="fact in stackFacts"
                            :key="fact.label"
                            class="grid grid-cols-[110px_1fr] gap-3"
                        >
                            <span class="text-[#94979C]">{{ fact.label }}</span>
                            <span class="text-[#F7F7F7] font-mono break-words">{{ fact.value }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="fact in machineFacts"
                            :key="fact.label"
                            class="grid grid-cols-[110px_1fr] gap-3"
                        >
                            <span class="text-[#94979C]">{{ fact.label }}</span>
                            <span class="text-[#F7F7F7] font-mono break-words">{{ fact.value }}</span>
                        </div>
                    </div>
                </div>
            </ResultsPanel>
        </div>
    </UContainer>
</template>

<script setup lang="ts">
import type { HttpRoute, RunEntry } from '~/types/run'
import { coresLabel, costLabel, formatThroughput } from '~/types/run'

const route = useRoute()
const id = route.params.id as string

// One file, one run. Nothing about this page's weight depends on how many
// other runs exist — which is the whole reason runs are published as static
// JSON rather than compiled into a queryable collection.
const runUrl = resultsApi(`${id}.json`)
const { data: entry } = await useAsyncData(
    `run-${id}`,
    () => $fetch<RunEntry>(runUrl).catch(() => null)
)

if (!entry.value) {
    throw createError({ statusCode: 404, statusMessage: 'Result not found', fatal: true })
}

// Past the 404 guard `entry` is always present, but the guard doesn't narrow
// the ref for the template — so unwrap once here rather than asserting at
// every use.
const run = computed(() => entry.value!.run)
const submitter = computed(() => {
    const { github, submitted_at, verified } = entry.value!

    return { github, submitted_at, verified }
})

// Runs are sharded by month (see shared/submission/run-document.mjs) so the
// directory stays readable once there are thousands of them.
const sourcePath = computed(() => `docs/data/runs/${id.slice(0, 4)}-${id.slice(4, 6)}/${id}.json`)
const http = computed(() => run.value.benchmarks.http ?? { routes: {} })
const cfspeedtest = computed(() => run.value.benchmarks.cfspeedtest ?? null)

// Friendly names for the at-a-glance strip.
const VARIATION_LABELS: Record<string, string> = {
    'frankenphp': 'FrankenPHP',
    'fpm-nginx': 'fpm-nginx',
    'fpm-apache': 'fpm-Apache'
}
const DB_LABELS: Record<string, string> = {
    sqlite: 'SQLite',
    mysql: 'MySQL',
    mariadb: 'MariaDB',
    pgsql: 'PostgreSQL',
    postgres: 'PostgreSQL',
    postgresql: 'PostgreSQL'
}

const specs = computed(() => {
    const e = run.value.environment
    const m = run.value.meta
    const driver = (e.laravel.drivers?.database as string | undefined)?.toLowerCase()
    const hasDbTest = http.value.routes?.db_read != null || run.value.benchmarks.php?.headline != null

    return [
        {
            label: 'Host',
            icon: 'i-lucide-server',
            value: m.provider || 'Self-hosted',
            // Cost in the currency the submitter is billed — no conversion,
            // no invented dollar sign.
            sub: [m.plan, m.datacenter, costLabel(m.cost)].filter(Boolean).join(' · ') || null
        },
        {
            label: 'Hardware',
            icon: 'i-lucide-cpu',
            value: `${coresLabel(e.server.cpu_cores)} · ${formatRam(e.server.ram)}`,
            sub: e.server.cpu_model
        },
        {
            label: 'Server',
            icon: 'i-lucide-boxes',
            value: VARIATION_LABELS[e.php.php_variation] ?? e.php.php_variation,
            sub: `PHP ${e.php.php_version}${e.php.octane ? ' · Octane' : ''}`
        },
        {
            label: 'Database',
            icon: 'i-lucide-database',
            value: driver ? (DB_LABELS[driver] ?? driver) : 'None',
            sub: driver ? (hasDbTest ? 'Query benchmarked' : 'Not benchmarked') : null
        }
    ]
})

// Ordered by real-world representativeness, matching the app's run view.
const ROUTES: Record<string, { label: string, description: string }> = {
    db_read: { label: 'DB read', description: '20 rows queried per request' },
    json: { label: 'JSON API', description: '25-item JSON payload' },
    static: { label: 'Static', description: 'Framework baseline — no database' },
    io: { label: 'I/O-bound', description: 'Simulated outbound call' }
}

// Gray = a typical request, amber = the tail.
const PERCENTILES = [
    { key: 'p50', human: 'Typical', color: '#94979C' },
    { key: 'p95', human: 'Slowest 5%', color: '#F79009' },
    { key: 'p99', human: 'Slowest 1%', color: '#F79009' }
] as const

const round = (v: number | null | undefined) => v == null ? '—' : Math.round(v).toLocaleString('en-US')

const formatMs = (ms: number) => ms >= 1 ? `${ms.toFixed(1)}ms` : `${Math.round(ms * 1000)}µs`

const routes = computed(() => {
    const routeData = http.value.routes ?? {}
    return Object.keys(ROUTES)
        .map(key => ({ key, data: (routeData as Record<string, HttpRoute | undefined>)[key] }))
        .filter((r): r is { key: string, data: HttpRoute } => r.data?.requests_per_second != null)
        .map(({ key, data }) => {
            const values: Record<string, number> = {}
            const widths: Record<string, number> = {}
            // Bars scale within each route to its own slowest percentile.
            const routeMax = Math.max(1, ...PERCENTILES.map(p => data[`${p.key}_ms`]).filter(v => v != null))
            PERCENTILES.forEach((p) => {
                const raw = data[`${p.key}_ms`]
                values[p.key] = raw != null ? Math.round(raw) : 0
                widths[p.key] = raw != null ? Math.max(2, (raw / routeMax) * 100) : 0
            })
            return {
                key,
                label: ROUTES[key]!.label,
                description: key === 'io' ? `Simulated ~${http.value.io_ms ?? 100}ms outbound call` : ROUTES[key]!.description,
                data,
                values,
                widths
            }
        })
})

// Above this relative standard deviation, in percent, the iterations behind a
// mean disagreed enough that the mean stops describing them. Kept in step with
// PhpBenchmarkResults::HIGH_VARIANCE_RSTDEV in the app.
const HIGH_VARIANCE_RSTDEV = 10

const isHighVariance = (rstdev?: number | null) => typeof rstdev === 'number' && rstdev > HIGH_VARIANCE_RSTDEV

/**
 * The four CRUD tiles, and whether they may share a bar scale.
 *
 * They may only when they measured the same unit of work, which is what
 * `statements` records. Runs from before schema 3 measured read as one SELECT
 * returning 100 rows while the other three ran 100 statements — roughly a
 * hundredth of the work, sat on the same scale and looking proportionally
 * faster for it. Those runs carry no statement count, so they get no bars.
 */
const phpOps = computed(() => {
    const h = run.value.benchmarks.php?.headline
    if (!h) return []
    const entries = [
        { key: 'create', label: 'Create', detail: 'INSERT per record', data: h.create },
        { key: 'read', label: 'Read', detail: 'SELECT per record, by id', data: h.read },
        { key: 'update', label: 'Update', detail: 'UPDATE per record, by id', data: h.update },
        { key: 'delete', label: 'Delete', detail: 'DELETE per record, by id', data: h.delete }
    ].filter(o => o.data?.milliseconds != null)
    const statements = entries.map(o => o.data!.statements)
    const comparable = entries.length > 0 && statements.every(n => n != null && n === statements[0])
    const maxMs = Math.max(1, ...entries.map(o => o.data!.milliseconds))
    return entries.map(o => ({
        key: o.key,
        label: o.label,
        detail: o.detail,
        milliseconds: o.data!.milliseconds,
        records: o.data!.records ?? 100,
        rstdev: o.data!.rstdev ?? null,
        iterations: o.data!.iterations ?? null,
        comparable,
        percent: comparable ? Math.max(2, (o.data!.milliseconds / maxMs) * 100) : null
    }))
})

const networkStats = computed(() => {
    const n = cfspeedtest.value
    if (!n) return []
    const fmt = (v: number | null | undefined) => v == null ? '—' : Number(v).toFixed(0)
    return [
        { label: 'Download', unit: 'mbps', value: fmt(n.download_mbps), icon: '/images/results/download-cloud.png' },
        { label: 'Upload', unit: 'mbps', value: fmt(n.upload_mbps), icon: '/images/results/upload-cloud.png' },
        { label: 'Latency', unit: 'ms', value: fmt(n.latency_ms), icon: '/images/results/latency-switch.png' }
    ]
})

const geekbench = computed(() => run.value.benchmarks.geekbench ?? null)

const DISK_COLS = [
    { key: 'speed_r', label: 'Read' },
    { key: 'speed_w', label: 'Write' },
    { key: 'speed_rw', label: 'Mixed' }
] as const

const diskRows = computed(() => {
    const rows = run.value.benchmarks.disk ?? []
    if (!rows.length) return []
    // Bars scale to the single fastest number across the whole matrix.
    const max = Math.max(1, ...rows.flatMap(r => DISK_COLS.map(c => r[c.key]).filter((v): v is number => v != null)))
    return rows.map(r => ({
        bs: r.bs,
        values: { speed_r: r.speed_r, speed_w: r.speed_w, speed_rw: r.speed_rw } as Record<string, number | null | undefined>,
        widths: Object.fromEntries(DISK_COLS.map(c => [c.key, r[c.key] != null ? Math.max(2, (r[c.key]! / max) * 100) : 0])) as Record<string, number>
    }))
})

const bool = (v: unknown) => v ? 'on' : 'off'
const formatRam = (ram: string) => {
    const mb = Number.parseFloat(ram)
    if (Number.isNaN(mb)) return ram
    return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${Math.round(mb)} MB`
}
const opcacheOn = (v: unknown) => v === true || v === '1' || v === 'on'

const stackFacts = computed(() => {
    const e = run.value.environment
    return [
        { label: 'Server', value: e.php.php_variation },
        { label: 'PHP', value: e.php.php_version },
        { label: 'Laravel', value: e.laravel.environment.laravel_version },
        { label: 'Octane', value: e.php.octane != null ? bool(e.php.octane) : null },
        { label: 'Database', value: databaseLabel.value ?? e.laravel.drivers?.database as string | undefined },
        // The CRUD subjects commit one statement at a time, so whether a commit
        // waits for the disk moves them by orders of magnitude. Without these,
        // a slow write result can't be told apart from a slow disk.
        { label: 'Durability', value: durabilityLabel.value },
        { label: 'DB filesystem', value: e.database?.filesystem },
        { label: 'OPcache', value: e.php.op_cache != null ? bool(opcacheOn(e.php.op_cache)) : null },
        // The knobs that explain more of the gap between two runs than the
        // hardware often does.
        { label: 'JIT', value: jit.value },
        { label: 'OPcache memory', value: ini.value['opcache.memory_consumption'] ? `${ini.value['opcache.memory_consumption']} MB` : null },
        { label: 'FPM workers', value: e.php.serving?.fpm_max_children ? `${e.php.serving.fpm_max_children} (${e.php.serving.fpm_pm ?? 'pm'})` : null },
        { label: 'Memory limit', value: e.php.memory_limit },
        { label: 'SAPI', value: e.php.php_server_api },
        { label: 'BenchKit', value: e.build_version }
    ].filter(f => f.value != null && f.value !== '')
})

const databaseLabel = computed(() => {
    const database = run.value.environment.database
    if (!database?.driver) return null
    return database.version ? `${database.driver} ${database.version}` : database.driver
})

// "journal_mode=wal · synchronous=normal" — named as the engine names them,
// so the value can be looked up rather than interpreted.
const durabilityLabel = computed(() => {
    const durability = run.value.environment.database?.durability ?? {}
    const settings = Object.entries(durability).filter(([, value]) => value != null)
    return settings.length ? settings.map(([key, value]) => `${key}=${value}`).join(' · ') : null
})

const ini = computed(() => run.value.environment.php.ini ?? {})

// opcache.jit is "disable"/"off"/"0" when off, or a mode like "tracing" or a
// 4-digit CRTO number when on — worth showing verbatim rather than as a
// yes/no, because which mode was used matters.
const jit = computed(() => {
    const value = ini.value['opcache.jit']

    if (value == null || value === '') return null

    return ['disable', 'off', '0'].includes(String(value)) ? 'Off' : String(value)
})

const machineFacts = computed(() => {
    const s = run.value.environment.server
    return [
        { label: 'CPU', value: s.cpu_model ? `${s.cpu_model} (${coresLabel(s.cpu_cores)})` : null },
        { label: 'RAM', value: formatRam(s.ram) },
        { label: 'OS', value: s.os }
    ].filter(f => f.value != null && f.value !== '')
})

useSeoMeta({
    title: () => entry.value ? `${entry.value.run.meta.label} — BenchKit result` : 'Result not found',
    description: () => entry.value ? `BenchKit run on ${entry.value.run.meta.provider || 'self-hosted'} (${entry.value.run.environment.php.php_variation}, PHP ${entry.value.run.environment.php.php_version})` : ''
})
</script>
