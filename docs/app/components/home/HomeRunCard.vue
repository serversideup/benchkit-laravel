<script setup lang="ts">
import type { RunIndex, RunEntry, HttpRoute } from '~/types/run'
import { primaryMetric, formatNumber, coresLabel, ramLabel, opcacheOn, monthlyCostLabel } from '~/types/run'

const props = defineProps<{
    summary: RunIndex
    run: RunEntry['run']
}>()

const server = computed(() => props.run.environment.server)
const php = computed(() => props.run.environment.php)

/** "Hetzner CPX41" — the machine, not the submitter's label for the run. */
const machine = computed(() => {
    const meta = props.run.meta

    return [meta.provider, meta.plan].filter(Boolean).join(' ') || meta.label
})

/**
 * Hardware and configuration only. The OS is the base image's, not the host's,
 * so in a container it says nothing about the machine being measured.
 */
const specs = computed(() => [
    coresLabel(server.value.cpu_cores),
    ramLabel(server.value.ram),
    `PHP ${php.value.php_version}`,
    monthlyCostLabel(props.summary.cost_amount, props.summary.cost_currency)
].filter(Boolean))

const ROUTES = [
    { key: 'static', label: 'Static' },
    { key: 'json', label: 'JSON' },
    { key: 'db_read', label: 'DB read' },
    { key: 'io', label: 'I/O' }
] as const

const routes = computed(() => ROUTES
    .map(route => ({ ...route, data: props.run.benchmarks.http?.routes?.[route.key] }))
    .filter((route): route is typeof route & { data: HttpRoute } => route.data != null))

/** JSON, then static, then DB read — the same priority the gallery ranks by. */
const headline = computed(() => routes.value.find(route => route.key === 'json')
    ?? routes.value.find(route => route.key === 'static')
    ?? routes.value.find(route => route.key === 'db_read')
    ?? routes.value[0])

/** Falls back to the flat summary when a run has no per-route detail. */
const fallback = computed(() => primaryMetric(props.summary))

/** Bars are relative to the fastest route in this run, not across runs. */
const fastest = computed(() => routes.value.reduce(
    (max, route) => Math.max(max, route.data.requests_per_second), 1
))

/** Fixed locale and time zone: the server and the browser must agree. */
const submittedOn = computed(() => new Date(props.summary.submitted_at).toLocaleDateString('en-US', {
    timeZone: 'UTC',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
}))
</script>

<template>
    <NuxtLink
        :to="`/results/${summary.run_id}`"
        class="group relative block h-full overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02] p-6 transition-colors duration-200 hover:border-white/20 sm:p-7"
    >
        <!-- Hairline along the top edge -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-12 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
        />

        <div class="flex items-center justify-between gap-4">
            <span class="text-xs text-neutral-500">
                Shared run
            </span>

            <div class="flex items-center gap-2">
                <!-- OPcache is on in every default image, so saying "on" says
                     nothing. Off is the exception, and the one worth flagging. -->
                <span
                    v-if="!opcacheOn(php.op_cache)"
                    class="text-xs text-amber-400/90"
                >
                    OPcache off
                </span>
                <span
                    v-if="summary.php_variation"
                    class="font-mono text-xs text-flame-400"
                >
                    {{ summary.php_variation }}
                </span>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <h3 class="font-sans text-xl font-semibold tracking-tight text-white">
                {{ machine }}
            </h3>
            <UIcon
                v-if="summary.verified"
                name="i-lucide-badge-check"
                class="size-4 shrink-0 text-flame-500"
                aria-label="Run by a maintainer"
            />
        </div>

        <!-- Flex rather than inline text: it breaks between specs and never
             inside one, so "16 GB" can't split across lines. -->
        <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-sm text-neutral-500">
            <template
                v-for="(spec, index) in specs"
                :key="spec"
            >
                <span
                    v-if="index"
                    class="text-neutral-700"
                >·</span>
                <span class="whitespace-nowrap">{{ spec }}</span>
            </template>
        </p>

        <div class="mt-7 border-t border-white/[0.08] pt-7">
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-5xl font-semibold tracking-tight text-white tabular-nums">
                    {{ formatNumber(headline?.data.requests_per_second ?? fallback?.rps) }}
                </span>
                <span class="font-mono text-sm text-neutral-400">req/sec</span>
            </div>
            <p class="mt-2 text-sm text-neutral-500">
                on the {{ headline?.label ?? fallback?.label }} route
            </p>

            <!-- All four routes, transposed to rows: the results page runs them
                 as columns, which needs a page width this card doesn't have.
                 Bar for the shape, p95 for what the slow requests cost. -->
            <div
                v-if="routes.length"
                class="mt-6 space-y-2.5"
            >
                <div
                    v-for="route in routes"
                    :key="route.key"
                    class="flex items-center gap-3"
                >
                    <span class="w-16 shrink-0 text-xs text-neutral-500">
                        {{ route.label }}
                    </span>
                    <span class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-white/[0.06]">
                        <span
                            class="block h-full rounded-full"
                            :class="route.key === headline?.key ? 'bg-flame-500' : 'bg-white/25'"
                            :style="{ width: `${Math.max(3, (route.data.requests_per_second / fastest) * 100)}%` }"
                        />
                    </span>
                    <span class="w-10 shrink-0 text-right font-mono text-xs text-neutral-300 tabular-nums">
                        {{ formatNumber(route.data.requests_per_second) }}
                    </span>
                    <span class="w-14 shrink-0 text-right font-mono text-[11px] text-neutral-600 tabular-nums">
                        {{ Math.round(route.data.p95_ms) }}ms
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-7 flex items-center justify-between gap-4 border-t border-white/[0.08] pt-5">
            <div class="flex min-w-0 items-center gap-2.5">
                <img
                    v-if="summary.github"
                    :src="`https://github.com/${summary.github}.png?size=48`"
                    :alt="summary.github"
                    class="size-6 shrink-0 rounded-full bg-white/5"
                    loading="lazy"
                >
                <span class="truncate text-sm text-neutral-400">
                    <template v-if="summary.github">@{{ summary.github }}</template>
                    <template v-else>Community submission</template>
                </span>
                <span class="hidden shrink-0 text-sm text-neutral-600 sm:inline">{{ submittedOn }}</span>
            </div>

            <span class="flex shrink-0 items-center gap-1.5 text-sm text-neutral-400 transition-colors group-hover:text-flame-400">
                View run
                <UIcon
                    name="i-lucide-arrow-up-right"
                    class="size-4"
                />
            </span>
        </div>
    </NuxtLink>
</template>
