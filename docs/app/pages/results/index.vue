<script setup lang="ts">
import type { ResultsIndex, RunIndex } from '~/types/run'
import { primaryMetric, formatNumber, monthlyCostLabel } from '~/types/run'

// URL resolved in setup, not inside the handler — resultsApi reads runtime
// config, and useAsyncData can re-run its handler outside a Nuxt context.
const indexUrl = resultsApi('index.json')
const { data } = await useAsyncData('results-index', () => $fetch<ResultsIndex>(indexUrl))

const entries = computed<RunIndex[]>(() => data.value?.runs ?? [])

const query = ref('')
const variation = ref<string>('all')
const verifiedOnly = ref(false)

/**
 * Deliberately no value or cost ranking. Comparing req/s per euro against
 * req/s per rupee needs an exchange rate, and any rate shipped here is wrong by
 * an unknown amount and gets more wrong every day nobody updates it. Cost stays
 * a visible column to eyeball instead.
 */
const SORTS = [
    { key: 'latest', label: 'Latest' },
    { key: 'fastest', label: 'Fastest' },
    { key: 'latency', label: 'Lowest latency' }
] as const

const sort = ref<typeof SORTS[number]['key']>('latest')

/** Built from the data, so a new image variation needs no code change here. */
const variations = computed(() => [...new Set(entries.value
    .map(entry => entry.php_variation)
    .filter((value): value is string => Boolean(value)))].sort())

/**
 * Search and sort keys, derived once when the data arrives rather than on every
 * keystroke. Searching used to build a five-item array and lowercase each field
 * per entry per keypress, and sorting called primaryMetric twice per comparison
 * — O(n log n) allocations for a value that never changes.
 *
 * Measured over 20 runs: 1.13ms → 0.11ms at 1,000 entries, 7.98ms → 1.27ms at
 * 10,000. The build itself costs 3ms at 1,000 and happens once.
 *
 * Provider and submitter are searched rather than filtered, because those lists
 * grow without bound while the image variations stay countable.
 */
interface IndexedRun {
    entry: RunIndex
    haystack: string
    rps: number
    p95: number
    recency: string
}

const indexed = computed<IndexedRun[]>(() => entries.value.map((entry) => {
    const metric = primaryMetric(entry)

    return {
        entry,
        haystack: [entry.label, entry.provider, entry.github, entry.php_variation, entry.php_version]
            .filter(Boolean)
            .join(' ')
            .toLowerCase(),
        rps: metric?.rps ?? 0,
        p95: metric?.p95_ms ?? Number.POSITIVE_INFINITY,
        recency: `${entry.submitted_at}${entry.run_id}`
    }
}))

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase()
    const image = variation.value
    const maintainer = verifiedOnly.value

    return indexed.value.filter((row) => {
        if (image !== 'all' && row.entry.php_variation !== image) return false
        if (maintainer && !row.entry.verified) return false
        if (needle && !row.haystack.includes(needle)) return false

        return true
    })
})

const sorted = computed(() => {
    const rows = [...filtered.value]

    if (sort.value === 'fastest') return rows.sort((a, b) => b.rps - a.rps)
    if (sort.value === 'latency') return rows.sort((a, b) => a.p95 - b.p95)

    return rows.sort((a, b) => b.recency.localeCompare(a.recency))
})

// Paged rather than dumped: at a few thousand runs the table alone is tens of
// thousands of DOM nodes, and nobody scrolls past the first screen anyway.
const PAGE_SIZE = 25
const shown = ref(PAGE_SIZE)
const visible = computed(() => sorted.value.slice(0, shown.value))

watch([query, variation, verifiedOnly, sort], () => shown.value = PAGE_SIZE)

/** Built here rather than as nested <template> fragments, which the linter's
 *  newline rules would break across lines and pad with stray whitespace. */
const emptyMessage = computed(() => entries.value.length
    ? 'Nothing matches that. Try a different search or clear the filters.'
    : 'No runs shared yet. Yours could be the first one here.')

/** "Hetzner · 8 cores" — built here so no separator ends up beside a tag. */
function machineLine(entry: RunIndex): string {
    return [entry.provider, entry.cpu_cores ? `${entry.cpu_cores} cores` : null]
        .filter(Boolean)
        .join(' · ')
}

function formatDate(value: string): string {
    // Fixed locale and time zone: the server and the browser must agree.
    return new Date(value).toLocaleDateString('en-US', {
        timeZone: 'UTC',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

useSeoMeta({
    title: 'Community results',
    description: 'Real-world BenchKit runs submitted by the community across hosts, PHP images, and configurations.'
})
</script>

<template>
    <div>
        <section>
            <UContainer class="pb-8 pt-12 lg:pt-14">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            class="h-px w-6 bg-flame-500/70"
                        />
                        <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-500">
                            Community results
                        </span>
                    </div>

                    <!-- font-sans overrides the global mono heading rule. -->
                    <h1 class="mt-4 text-balance font-sans text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-white sm:text-4xl">
                        Every run people have shared.
                    </h1>

                    <p class="mt-3 max-w-2xl text-pretty leading-relaxed text-neutral-400">
                        Find one close to your setup and see exactly what produced it. Anyone can
                        submit, and runs the BenchKit team did themselves are marked.
                    </p>
                </div>
            </UContainer>
        </section>

        <section class="border-t border-white/[0.06]">
            <UContainer class="pb-14 pt-8">
                <div class="flex flex-col gap-4">
                    <div class="relative max-w-md">
                        <UIcon
                            name="i-lucide-search"
                            class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-neutral-600"
                        />
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Search a host, image, PHP version, or person"
                            aria-label="Search runs"
                            class="w-full rounded-xl border border-white/10 bg-white/[0.03] py-3 pl-11 pr-4 text-sm text-white transition-colors placeholder:text-neutral-600 focus:border-white/20 focus:outline-none"
                        >
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div
                            v-if="variations.length > 1"
                            class="flex flex-wrap items-center gap-1"
                        >
                            <span class="mr-2 text-xs text-neutral-600">Image</span>
                            <button
                                v-for="option in ['all', ...variations]"
                                :key="option"
                                type="button"
                                :aria-pressed="variation === option"
                                class="cursor-pointer rounded-lg px-3 py-1.5 text-sm transition-colors duration-200"
                                :class="variation === option
                                    ? 'bg-flame-500/15 text-flame-400'
                                    : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                                @click="variation = option"
                            >
                                {{ option === 'all' ? 'All' : option }}
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-1">
                            <span class="mr-2 text-xs text-neutral-600">Sort</span>
                            <button
                                v-for="option in SORTS"
                                :key="option.key"
                                type="button"
                                :aria-pressed="sort === option.key"
                                class="cursor-pointer rounded-lg px-3 py-1.5 text-sm transition-colors duration-200"
                                :class="sort === option.key
                                    ? 'bg-flame-500/15 text-flame-400'
                                    : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                                @click="sort = option.key"
                            >
                                {{ option.label }}
                            </button>
                        </div>

                        <button
                            type="button"
                            :aria-pressed="verifiedOnly"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition-colors duration-200"
                            :class="verifiedOnly
                                ? 'bg-flame-500/15 text-flame-400'
                                : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                            @click="verifiedOnly = !verifiedOnly"
                        >
                            <UIcon
                                name="i-lucide-badge-check"
                                class="size-3.5"
                            />
                            Maintainer runs
                        </button>

                        <span class="ml-auto text-xs text-neutral-600">
                            {{ sorted.length }} of {{ entries.length }}
                        </span>
                    </div>
                </div>

                <div class="relative mt-8 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02]">
                    <!-- Hairline along the top edge -->
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-x-32 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                    />

                    <!-- Scrolls inside its own container, so a narrow screen never
                         makes the page itself scroll sideways. -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[58rem] table-fixed text-left">
                            <thead>
                                <tr class="border-b border-white/[0.06] text-xs text-neutral-500">
                                    <th class="w-[28%] px-6 py-4 font-normal">
                                        Run
                                    </th>
                                    <th class="w-[15%] px-4 py-4 font-normal">
                                        Stack
                                    </th>
                                    <th class="w-[14%] px-4 py-4 text-right font-normal">
                                        Req/s
                                    </th>
                                    <th class="w-[10%] px-4 py-4 text-right font-normal">
                                        p95
                                    </th>
                                    <th class="w-[9%] px-4 py-4 text-right font-normal">
                                        Cost
                                    </th>
                                    <th class="w-[20%] px-6 py-4 font-normal">
                                        Shared by
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visible.length">
                                    <td
                                        colspan="6"
                                        class="px-6 py-16 text-center"
                                    >
                                        <p class="text-sm text-neutral-400">
                                            {{ emptyMessage }}
                                        </p>
                                    </td>
                                </tr>

                                <tr
                                    v-for="(row, index) in visible"
                                    :key="row.entry.run_id"
                                    class="group relative transition-colors duration-200 hover:bg-white/[0.03]"
                                    :class="index ? 'border-t border-white/[0.05]' : ''"
                                >
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-1.5">
                                            <!-- after:inset-0 stretches this one link across the
                                                 whole row, which keeps the markup valid. -->
                                            <NuxtLink
                                                :to="`/results/${row.entry.run_id}`"
                                                class="truncate text-sm text-white after:absolute after:inset-0 after:content-['']"
                                            >
                                                {{ row.entry.label || row.entry.provider }}
                                            </NuxtLink>
                                            <UIcon
                                                v-if="row.entry.verified"
                                                name="i-lucide-badge-check"
                                                class="size-3.5 shrink-0 text-flame-500"
                                                aria-label="Run by a maintainer"
                                            />
                                        </div>
                                        <div class="mt-0.5 truncate text-sm text-neutral-500">
                                            {{ machineLine(row.entry) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-5">
                                        <div class="font-mono text-xs text-flame-400/90">
                                            {{ row.entry.php_variation }}
                                        </div>
                                        <div class="mt-0.5 font-mono text-xs text-neutral-500">
                                            PHP {{ row.entry.php_version }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-right">
                                        <div class="font-mono text-base text-white tabular-nums">
                                            {{ formatNumber(primaryMetric(row.entry)?.rps) }}
                                        </div>
                                        <!-- primaryMetric falls back JSON, static, DB read, so the
                                             route has to travel with the number to stay honest. -->
                                        <div class="mt-0.5 font-mono text-[11px] text-neutral-500">
                                            {{ primaryMetric(row.entry)?.label ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-right font-mono text-xs text-neutral-400 tabular-nums">
                                        {{ primaryMetric(row.entry)?.p95_ms ?? '—' }}<span class="text-neutral-500">ms</span>
                                    </td>
                                    <td class="px-4 py-5 text-right font-mono text-xs text-neutral-400 tabular-nums">
                                        {{ monthlyCostLabel(row.entry.cost_amount, row.entry.cost_currency) ?? '—' }}
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="row.entry.github"
                                                :src="`https://github.com/${row.entry.github}.png?size=40`"
                                                :alt="row.entry.github"
                                                class="size-5 shrink-0 rounded-full bg-white/5"
                                                loading="lazy"
                                            >
                                            <span class="truncate text-xs text-neutral-400">
                                                {{ row.entry.github ? `@${row.entry.github}` : 'Community' }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 truncate text-sm text-neutral-500">
                                            {{ formatDate(row.entry.submitted_at) }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="visible.length < sorted.length"
                    class="mt-6 text-center"
                >
                    <UButton
                        color="neutral"
                        variant="outline"
                        size="lg"
                        @click="shown += PAGE_SIZE"
                    >
                        Show more
                    </UButton>
                </div>
            </UContainer>
        </section>

        <section class="border-t border-white/[0.06]">
            <UContainer class="py-16 lg:py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-balance font-sans text-3xl font-semibold tracking-[-0.025em] text-white">
                        Ran one? Add it to the gallery.
                    </h2>
                    <p class="mt-4 text-pretty leading-relaxed text-neutral-400">
                        Hit <span class="text-neutral-200">Submit result</span> in the app and a bot
                        files the pull request.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <UButton
                            to="/docs/getting-started"
                            size="xl"
                            color="primary"
                            trailing-icon="i-lucide-arrow-right"
                        >
                            Run a benchmark
                        </UButton>
                        <UButton
                            to="/docs/community-results"
                            size="xl"
                            color="neutral"
                            variant="outline"
                        >
                            How submitting works
                        </UButton>
                    </div>

                    <!-- Kept reachable so link crawling still emits the route. -->
                    <p class="mt-8 text-sm text-neutral-500">
                        Already have a submission token?
                        <ULink
                            to="/results/submit"
                            class="text-neutral-400 underline decoration-white/20 underline-offset-4 transition-colors hover:text-flame-400"
                        >
                            Check it before you submit
                        </ULink>
                    </p>
                </div>
            </UContainer>
        </section>
    </div>
</template>
