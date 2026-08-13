<template>
    <UContainer class="mx-auto max-w-[960px] pb-20 pt-10">
        <UButton
            to="/results"
            variant="ghost"
            color="neutral"
            size="sm"
            icon="i-lucide-arrow-left"
            class="-ml-2.5 mb-8"
        >
            All results
        </UButton>

        <!-- Header -->
        <div class="mb-10">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-balance text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-white sm:text-4xl">
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
                </div>
                <div class="mt-4 flex items-center gap-2 text-sm text-neutral-400">
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
                            class="hover:text-white"
                        >@{{ submitter.github }}</a>
                        <span class="text-neutral-500">·</span>
                    </template>
                    <span>{{ submitter.submitted_at }}</span>
                    <span class="text-neutral-600">·</span>
                    <a
                        :href="`https://github.com/${SUBMISSION_REPO}/blob/main/${sourcePath}`"
                        target="_blank"
                        class="inline-flex items-center gap-1 transition-colors hover:text-white"
                    >
                        <UIcon
                            name="i-lucide-file-json"
                            class="size-3.5"
                        /> Source
                    </a>
                    <!-- Also in the end-cap at the foot of the page. Not a
                         duplicate so much as a second moment: this one is for a
                         reader who already knows they want the thread, and the
                         question usually forms up here — at the spec strip,
                         four panels before the end-cap comes into view. -->
                    <template v-if="issueUrl">
                        <span class="text-neutral-600">·</span>
                        <a
                            :href="issueUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1 transition-colors hover:text-white"
                        >
                            <UIcon
                                name="i-lucide-message-square"
                                class="size-3.5"
                            /> Discuss
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <!-- At-a-glance spec strip: whose machine, running what.
             Hairline dividers rather than four boxes — these are one fact read
             left to right, and boxing each of them competed with the panels
             below for the same attention. -->
        <div class="mb-10 grid grid-cols-2 gap-y-8 rounded-2xl border border-white/10 bg-white/[0.02] p-6 sm:p-8 lg:grid-cols-4 lg:gap-x-8">
            <div
                v-for="(spec, index) in specs"
                :key="spec.label"
                :class="index > 0 ? 'lg:border-l lg:border-white/10 lg:pl-8' : ''"
            >
                <div class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-500">
                    <UIcon
                        :name="spec.icon"
                        class="size-3.5"
                    /> {{ spec.label }}
                </div>
                <div class="mt-2.5 break-words text-lg font-semibold leading-tight text-white">
                    {{ spec.value }}
                </div>
                <div
                    v-if="spec.sub"
                    class="mt-1 break-words font-mono text-xs text-neutral-500"
                >
                    {{ spec.sub }}
                </div>
            </div>
        </div>

        <!-- Panels stand as separate cards rather than divisions of one long
             box: it gives each measurement its own edge and lets the page
             breathe at the homepage's rhythm. -->
        <div class="space-y-4">
            <!-- Conditions that change how the whole run should be read, in
                 words, before the numbers they apply to. Quiet on a normal run
                 — which is most of them — so that when one does appear it is
                 worth stopping for. -->
            <div
                v-for="caveat in caveats"
                :key="caveat.key"
                class="rounded-2xl border border-[#F79009]/30 bg-[#F79009]/[0.06] p-6 sm:p-8"
            >
                <p class="text-lg font-semibold tracking-[-0.01em] text-[#F79009]">
                    {{ caveat.title }}
                </p>
                <p class="mt-2 max-w-2xl text-pretty text-sm leading-relaxed text-neutral-300">
                    {{ caveat.detail }}
                </p>
            </div>

            <!-- HTTP throughput -->
            <ResultsPanel
                v-if="routes.length"
                title="Web server load test"
                eyebrow="oha"
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

                <p class="mt-2 text-xs text-neutral-500">
                    Saturation test — connections held open to find max throughput, so response times
                    include time spent queued.
                    <NuxtLink
                        to="/docs/benchmarks/web-server-load-test"
                        class="text-neutral-400 underline underline-offset-4 decoration-white/20 transition-colors hover:text-neutral-300 hover:decoration-white/40"
                    >
                        Learn more
                    </NuxtLink>
                </p>

                <!-- Mobile: one route per block -->
                <div class="mt-5 flex flex-col divide-y divide-white/10 md:hidden">
                    <div
                        v-for="benchRoute in routes"
                        :key="`m-${benchRoute.key}`"
                        class="py-5 first:pt-0 last:pb-0"
                    >
                        <p class="text-sm font-medium text-neutral-300">
                            {{ benchRoute.label }}
                        </p>
                        <p class="text-xs text-neutral-400 mt-0.5">
                            {{ benchRoute.description }}
                        </p>
                        <div class="mt-2">
                            <p class="text-5xl text-white font-mono font-medium leading-none tabular-nums">
                                {{ round(benchRoute.data.requests_per_second) }}
                            </p>
                            <p class="mt-1.5 text-sm text-neutral-400 font-mono">
                                req/s
                            </p>
                        </div>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <div
                                v-for="p in PERCENTILES"
                                :key="`m-${benchRoute.key}-${p.key}`"
                                class="flex items-center gap-2"
                            >
                                <span class="w-24 shrink-0 text-xs text-neutral-400">{{ p.human }} <span class="text-neutral-500">{{ p.key }}</span></span>
                                <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums text-neutral-300">{{ benchRoute.values[p.key] }}ms</span>
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
                        <p class="text-sm font-medium text-neutral-300">
                            {{ benchRoute.label }}
                        </p>
                        <p class="text-xs text-neutral-400 mt-0.5">
                            {{ benchRoute.description }}
                        </p>
                        <div class="mt-auto pt-4">
                            <p class="text-4xl text-white font-mono font-medium leading-none tabular-nums">
                                {{ round(benchRoute.data.requests_per_second) }}
                            </p>
                            <p class="mt-1.5 text-sm text-neutral-400 font-mono">
                                req/s
                            </p>
                        </div>
                    </div>

                    <template
                        v-for="p in PERCENTILES"
                        :key="p.key"
                    >
                        <span class="text-xs text-neutral-400">{{ p.human }} <span class="text-neutral-500">{{ p.key }}</span></span>
                        <div
                            v-for="benchRoute in routes"
                            :key="`${benchRoute.key}-${p.key}`"
                            class="flex items-center gap-2"
                        >
                            <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums text-neutral-300">{{ benchRoute.values[p.key] }}ms</span>
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
                eyebrow="phpbench"
            >
                <template #aside>
                    <ResultsChip>{{ phpOps[0]?.records }} records per operation</ResultsChip>
                </template>

                <p
                    v-if="phpOps[0]?.comparable"
                    class="mt-2 text-xs text-neutral-400"
                >
                    ↓ Lower is better — time to run {{ phpOps[0]?.records }} statements, one record each
                </p>
                <p
                    v-else
                    class="mt-2 text-xs text-neutral-400"
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
                            <span class="text-sm font-medium text-neutral-400">{{ op.label }}</span>
                        </p>
                        <p class="mt-1.5 text-4xl text-white font-mono font-medium leading-none">
                            {{ formatMs(op.milliseconds) }}
                        </p>
                        <ResultsBar
                            v-if="op.percent !== null"
                            class="mt-3.5 h-2"
                            :percent="op.percent"
                        />
                        <p class="mt-2 text-xs text-neutral-500 leading-snug">
                            {{ op.detail }}
                        </p>
                        <!-- A mean is only worth its digits if the iterations
                             behind it agreed. When they didn't, say so on the
                             number rather than in a footnote nobody reads. -->
                        <p
                            v-if="op.rstdev !== null"
                            class="mt-1 text-xs font-mono leading-snug"
                            :class="isHighVariance(op.rstdev) ? 'text-[#F79009]' : 'text-neutral-500'"
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
                eyebrow="cfspeedtest"
            >
                <p class="mt-2 text-sm text-neutral-400">
                    Measured from the server to Cloudflare's nearest edge — this latency rides on every external request the app makes.
                </p>

                <div class="mt-6 flex flex-wrap items-end gap-x-10 gap-y-6">
                    <div
                        v-for="stat in networkStats"
                        :key="stat.label"
                    >
                        <p class="flex items-center gap-1.5 text-sm font-medium text-neutral-400">
                            <img
                                :src="stat.icon"
                                alt=""
                                class="w-3.5"
                            > {{ stat.label }}
                        </p>
                        <p class="flex items-baseline gap-2 mt-1">
                            <span class="text-4xl text-white font-mono font-medium leading-none">{{ stat.value }}</span>
                            <span class="text-sm text-neutral-400 font-mono">{{ stat.unit }}</span>
                        </p>
                    </div>
                </div>
            </ResultsPanel>

            <!-- Hardware: Geekbench + disk throughput -->
            <ResultsPanel
                v-if="geekbench || diskRows.length"
                title="Hardware"
                eyebrow="yabs"
            >
                <template
                    v-if="geekbench?.url"
                    #aside
                >
                    <a
                        :href="geekbench.url"
                        target="_blank"
                        class="text-xs font-mono text-neutral-300 underline underline-offset-4 decoration-white/20 hover:decoration-neutral-400"
                    >View on Geekbench &rarr;</a>
                </template>

                <p class="mt-2 text-xs text-neutral-400">
                    ↑ Higher is better
                </p>

                <div
                    v-if="geekbench"
                    class="mt-6 flex flex-wrap items-end gap-x-12 gap-y-6"
                >
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-medium text-neutral-400">
                            <img
                                src="/images/results/single-core.png"
                                alt=""
                                class="w-3.5"
                            > Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} single-core
                        </p>
                        <p class="mt-1 text-4xl text-white font-mono font-medium leading-none tabular-nums">
                            {{ round(geekbench.single) }}
                        </p>
                    </div>
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-medium text-neutral-400">
                            <img
                                src="/images/results/multi-core.png"
                                alt=""
                                class="w-3.5"
                            > Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} multi-core
                        </p>
                        <p class="mt-1 text-4xl text-white font-mono font-medium leading-none tabular-nums">
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
                        <p class="text-xs text-neutral-300 font-mono mb-2">
                            Disk I/O <span class="text-neutral-500">&middot; {{ row.bs }}</span>
                        </p>
                        <div class="flex flex-col gap-2.5">
                            <div
                                v-for="col in DISK_COLS"
                                :key="`${row.bs}-${col.key}`"
                                class="flex items-center gap-3"
                            >
                                <span class="w-16 shrink-0 text-xs text-neutral-400">{{ col.label }}</span>
                                <ResultsBar
                                    class="flex-1 h-2"
                                    :percent="row.widths[col.key]"
                                />
                                <span class="w-[84px] shrink-0 text-right text-xs text-neutral-300 font-mono tabular-nums">{{ formatThroughput(row.values[col.key]) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </ResultsPanel>

            <!-- Environment. Three groups rather than one list: what served the
                 requests, what it was tuned with, and what it ran on. Each is
                 hidden when empty, so a host that exposes no serving config
                 renders a shorter panel instead of a column of blanks. -->
            <ResultsPanel
                title="Environment"
                eyebrow="Configuration"
            >
                <!-- Multi-column rather than a grid: the groups are different
                     lengths (runtime runs long, machine is three rows), and a
                     two-cell grid left a quarter of the panel empty. Columns
                     balance the flow instead. -->
                <div class="mt-8 sm:columns-2 sm:gap-x-12">
                    <div
                        v-for="group in environmentGroups"
                        :key="group.title"
                        class="mb-10 break-inside-avoid last:mb-0"
                    >
                        <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-500">
                            {{ group.title }}
                        </p>
                        <dl class="mt-4 flex flex-col divide-y divide-white/[0.06] text-sm">
                            <!-- A fixed label column split directive names down
                                 the middle ("pm.max_childre / n"), because a mono
                                 identifier has no spaces to wrap at. Auto-sizing
                                 the column to the longest label in the group and
                                 letting the value take the rest keeps every row
                                 on one line. -->
                            <div
                                v-for="fact in group.facts"
                                :key="fact.label"
                                class="grid grid-cols-[minmax(0,auto)_1fr] items-baseline gap-x-6 py-2.5 first:pt-0 last:pb-0"
                            >
                                <dt class="whitespace-nowrap text-neutral-400">
                                    {{ fact.label }}
                                </dt>
                                <dd class="break-words text-right font-mono text-[13px] text-white">
                                    {{ fact.value }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <p
                    v-if="run.environment.php_environment_source === 'cli'"
                    class="mt-6 text-xs text-neutral-400 leading-relaxed"
                >
                    This run had no web server load test, so the PHP settings above were read from the
                    command-line process that assembled it rather than from a web server. They are a
                    different SAPI, with their own OPcache and memory limit.
                </p>
            </ResultsPanel>
        </div>

        <!-- Discussion end-cap.
             Deliberately not a full-width banner. A banner is a claim about
             priority, and the point of this page is the measurements — someone
             arrives to read a number, and only some of them will have a
             question afterwards. So it sits where that question actually forms,
             after the data, sized like the next thing you might do rather than
             like the thing the page wants from you. It was a 12px text link in
             a metadata row before, which was the opposite mistake. -->
        <section
            v-if="issueUrl"
            class="mt-4 flex flex-col gap-6 rounded-2xl border border-white/10 bg-white/[0.02] p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8"
        >
            <div class="max-w-lg">
                <h2 class="text-lg font-semibold tracking-[-0.01em] text-white">
                    Discuss this result
                </h2>
                <!-- Says nothing about the issue being open or closed: the bot
                     closes every one it files (--reason completed), so every
                     result here links to a closed thread. GitHub takes comments
                     on those all the same. -->
                <p class="mt-2 text-pretty text-sm leading-relaxed text-neutral-400">
                    Comment on the original GitHub issue where this result was submitted. Feel free to
                    ask more on how the host was set up or any other questions you have.
                </p>
            </div>

            <UButton
                :to="issueUrl"
                target="_blank"
                size="lg"
                color="neutral"
                variant="outline"
                trailing-icon="i-lucide-arrow-up-right"
                class="shrink-0"
            >
                Leave a comment
            </UButton>
        </section>
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
/** Where results live and where they are discussed. Mirrors SubmissionIssue::REPO in the app. */
const SUBMISSION_REPO = 'serversideup/benchkit-laravel'

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

    // One headline and one qualifier per card, in that order, every time.
    // The cards used to disagree about their own shape — host chained three
    // facts into a line that wrapped, hardware put two different units in the
    // headline so neither was labelled, and database padded its qualifier with
    // "Query benchmarked" to look symmetrical. A strip like this is read by
    // scanning down one column and across, and that only works if every cell
    // means the same kind of thing.
    return [
        {
            label: 'Host',
            icon: 'i-lucide-server',
            value: m.provider || 'Self-hosted',
            // Price leads, plan qualifies it. The gallery ranks on throughput
            // per unit of cost, so this is one of the few facts the page exists
            // to state — it was previously read last in a three-item chain that
            // wrapped. The datacenter moves to the Environment panel; it is
            // worth recording and not worth a quarter of this strip.
            sub: cost.value
                ? `${cost.value}${m.plan ? ` (${m.plan})` : ''}`
                : m.plan || null
        },
        {
            label: 'Hardware',
            icon: 'i-lucide-cpu',
            // The CPU model is not here: it can run to "AMD EPYC 9354P 32-Core
            // Processor", which no column this narrow can hold. It reads
            // properly in the Environment panel, where there is room for it.
            value: coresLabel(e.server.cpu_cores),
            sub: formatRam(e.server.ram) ? `${formatRam(e.server.ram)} RAM` : null
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
            // The version, to match the PHP version above it. "Not benchmarked"
            // stays because it changes how the panels below should be read;
            // "Query benchmarked" went because it only ever restated the page.
            sub: driver ? (hasDbTest ? (e.database?.version ?? null) : 'Not benchmarked') : null
        }
    ]
})

// Ordered by real-world representativeness, matching the app's run view.
// A ladder: each route adds one thing to the one before it — serialization,
// then a database, then a blocking wait. The deltas are the story.
const ROUTES: Record<string, { label: string, description: string }> = {
    static: { label: 'Static', description: 'Framework baseline — no database' },
    json: { label: 'JSON API', description: '25-item JSON payload' },
    db_read: { label: 'DB read', description: '20 rows queried per request' },
    io: { label: 'I/O-bound', description: 'Simulated outbound call' }
}

// Gray = a typical request, amber = the tail.
/**
 * One measurement read at three points, so the bars are one hue at three
 * weights rather than three colours. They were neutral, amber, amber — which
 * filled every panel with twelve saturated bars and left the accent meaning
 * nothing by the time something actually needed flagging. Warmth still climbs
 * with the tail, just quietly enough that the numbers stay the loudest thing.
 */
const PERCENTILES = [
    { key: 'p50', human: 'Typical', color: 'rgb(163 163 163 / 0.40)' },
    { key: 'p95', human: 'Slowest 5%', color: 'rgb(245 103 63 / 0.35)' },
    { key: 'p99', human: 'Slowest 1%', color: 'rgb(245 103 63 / 0.60)' }
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

const present = (facts: { label: string, value: unknown }[]) => facts.filter(f => f.value != null && f.value !== '')

// The runtime that served the requests. Split out from tuning because these
// answer a different question — "what was this?" rather than "how was it set
// up?" — and because a host BenchKit has never seen can still fill most of it.
const stackFacts = computed(() => {
    const e = run.value.environment
    const r = e.php.runtime ?? {}

    return present([
        { label: 'Server', value: serverLabel.value },
        { label: 'Mode', value: r.mode === 'worker' ? 'Worker (persistent)' : r.mode === 'process-per-request' ? 'Process per request' : null },
        // Named with what it counts. Twenty FPM children and eight FrankenPHP
        // threads are both "workers" and are not the same quantity.
        { label: 'Workers', value: r.workers ? `${r.workers}${r.workers_source ? ` (${r.workers_source})` : ''}` : null },
        { label: 'Web server', value: r.front_end ? [r.front_end, r.front_end_version].filter(Boolean).join(' ') : null },
        { label: 'SAPI', value: e.php.php_server_api },
        { label: 'PHP', value: e.php.php_version },
        { label: 'Laravel', value: e.laravel.environment.laravel_version },
        { label: 'Database', value: databaseLabel.value ?? e.laravel.drivers?.database as string | undefined }
    ])
})

// Everything an operator chose, which is what a reader comparing two runs on
// the same hardware is actually looking for. The server-specific directives
// render generically, so a runtime nobody has written a template for still
// shows its tuning instead of showing nothing.
const tuningFacts = computed(() => {
    const e = run.value.environment

    return present([
        // Durability and the filesystem live down here with the other raw
        // directives rather than up in Runtime. They matter — a commit that
        // waits for the disk costs orders of magnitude more than one that does
        // not — but they are settings to look up, not facts to scan, and among
        // Server/Mode/PHP/Laravel they read as something you were supposed to
        // already understand. The case where they change how the numbers should
        // be read is a caveat instead, at the top, in words.
        { label: 'Write durability', value: durabilityLabel.value },
        { label: 'DB filesystem', value: e.database?.filesystem },
        { label: 'OPcache', value: e.php.op_cache != null ? bool(opcacheOn(e.php.op_cache)) : null },
        { label: 'JIT', value: jit.value },
        { label: 'OPcache memory', value: ini.value['opcache.memory_consumption'] ? `${ini.value['opcache.memory_consumption']} MB` : null },
        { label: 'Memory limit', value: e.php.memory_limit },
        { label: 'Debug mode', value: e.laravel.environment.debug_mode === true ? 'On' : null },
        ...Object.entries(e.php.runtime?.settings ?? {}).map(([label, value]) => ({ label, value }))
    ])
})

const serverLabel = computed(() => {
    const e = run.value.environment
    const variation = e.php.php_variation

    return variation ? (VARIATION_LABELS[variation] ?? variation) : (e.php.runtime?.server ?? null)
})

/**
 * The issue this result was submitted in. A published number invites questions
 * — about the host, the plan, why one figure looks off — and this is where they
 * already have somewhere to go.
 */
const issueUrl = computed(() => entry.value?.issue ? `https://github.com/${SUBMISSION_REPO}/issues/${entry.value.issue}` : null)

/** Cost in the currency the submitter is billed — no conversion, no invented dollar sign. */
const cost = computed(() => costLabel(run.value.meta.cost))

/** Filesystems that are memory pretending to be storage. */
const MEMORY_FILESYSTEMS = ['tmpfs', 'ramfs', 'memory']

/**
 * Written for someone who has never heard of fsync, because that is who reads
 * this page. Each one says what is wrong with the numbers and why, in the terms
 * a Laravel developer already has — not in the terms the setting is named in.
 */
const caveats = computed(() => {
    const e = run.value.environment
    const found: { key: string, title: string, detail: string }[] = []
    const durability = e.database?.durability ?? {}

    if (MEMORY_FILESYSTEMS.includes(String(e.database?.filesystem ?? '').toLowerCase())) {
        found.push({
            key: 'memory-database',
            title: 'These write speeds came from a database in memory',
            detail: 'The database was stored in RAM rather than on a disk. Writing to memory is far faster than writing to any real drive, so the Create, Update, and Delete figures here are not what this host would do in production.'
        })
    } else if (Object.values(durability).some(v => ['off', '0'].includes(String(v).toLowerCase()))) {
        found.push({
            key: 'unsafe-writes',
            title: 'The database was not waiting for writes to reach the disk',
            detail: 'It was configured to report a write as finished before the drive had actually stored it, which is faster but loses recent data in a crash. The Create, Update, and Delete figures are higher than a normally configured database would produce.'
        })
    }

    if (e.laravel.environment.debug_mode === true) {
        found.push({
            key: 'debug',
            title: 'This server is faster than these numbers say',
            detail: 'The app was running in debug mode, so Laravel built a stack trace on every single request — something it never does in production. These numbers are not truly comparable with actual production results.'
        })
    }

    if (e.php.op_cache != null && !opcacheOn(e.php.op_cache)) {
        found.push({
            key: 'opcache',
            title: 'This server is much faster than these numbers say',
            detail: 'OPcache was off, so PHP recompiled the whole application from source on every request. Practically no production host runs this way, so this result cannot be compared with the rest of the gallery.'
        })
    }

    return found
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

/**
 * Ordered outward-in: who it was rented from, what the machine is, what it was
 * running, how that was tuned. The spec strip at the top is the glance; this is
 * the record, so nothing the submitter told us has to be dropped from one to
 * fit the other — the datacenter lives here rather than competing for a quarter
 * of the strip.
 */
const environmentGroups = computed(() => [
    { title: 'Host', facts: hostFacts.value },
    { title: 'Runtime', facts: stackFacts.value },
    { title: 'Machine', facts: machineFacts.value },
    { title: 'Tuning', facts: tuningFacts.value }
].filter(group => group.facts.length))

const hostFacts = computed(() => {
    const m = run.value.meta

    return present([
        { label: 'Provider', value: m.provider || 'Self-hosted' },
        { label: 'Plan', value: m.plan },
        { label: 'Datacenter', value: m.datacenter },
        { label: 'Cost', value: cost.value }
    ])
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
