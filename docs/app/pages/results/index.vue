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

/** Counterweight for the page header, and a read on how deep the gallery is. */
const stats = computed(() => [
    { label: 'Runs', value: entries.value.length },
    { label: 'Providers', value: new Set(entries.value.map(entry => entry.provider).filter(Boolean)).size },
    { label: 'Images', value: variations.value.length }
])

/** Provider and submitter are searched rather than filtered: the lists grow. */
function matchesQuery(entry: RunIndex, needle: string): boolean {
    return [entry.label, entry.provider, entry.github, entry.php_variation, entry.php_version]
        .filter(Boolean)
        .some(field => String(field).toLowerCase().includes(needle))
}

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase()

    return entries.value.filter((entry) => {
        if (variation.value !== 'all' && entry.php_variation !== variation.value) return false
        if (verifiedOnly.value && !entry.verified) return false
        if (needle && !matchesQuery(entry, needle)) return false

        return true
    })
})

const sorted = computed(() => [...filtered.value].sort((a, b) => {
    if (sort.value === 'fastest') {
        return (primaryMetric(b)?.rps ?? 0) - (primaryMetric(a)?.rps ?? 0)
    }

    if (sort.value === 'latency') {
        return (primaryMetric(a)?.p95_ms ?? Infinity) - (primaryMetric(b)?.p95_ms ?? Infinity)
    }

    return b.submitted_at.localeCompare(a.submitted_at) || b.run_id.localeCompare(a.run_id)
}))

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
            <UContainer class="pb-10 pt-16 lg:pt-20">
                <!-- Stats sit opposite the heading so the header isn't a narrow
                     column against an empty right half. -->
                <div class="grid gap-10 xl:grid-cols-[1fr_auto] xl:items-end xl:gap-20">
                    <div class="max-w-2xl">
                        <p class="text-sm text-neutral-500">
                            Community results
                        </p>

                        <!-- font-sans overrides the global mono heading rule. -->
                        <h1 class="mt-3 text-balance font-sans text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-white sm:text-5xl">
                            <span class="block">Every run people</span>
                            <span class="block text-neutral-500">have shared.</span>
                        </h1>

                        <p class="mt-5 text-pretty text-lg leading-relaxed text-neutral-400">
                            Real hardware, real configurations, real numbers. Find one close to your
                            setup and see exactly what produced it.
                        </p>

                        <!-- Disclosure as a quiet line rather than a warning box: it's
                         context for reading the table, not an alarm. -->
                        <p class="mt-5 text-pretty text-sm leading-relaxed text-neutral-600">
                            Anyone can submit, and every run happens on the submitter's own machine, so
                            these are real-world data points rather than certified scores. A maintainer
                            reviews each one before it lands, and runs the BenchKit team ran themselves
                            are marked.
                        </p>
                    </div>

                    <dl class="flex gap-10 xl:gap-12">
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                        >
                            <dt class="text-xs text-neutral-600">
                                {{ stat.label }}
                            </dt>
                            <dd class="mt-2 font-mono text-3xl font-semibold tracking-tight text-white tabular-nums">
                                {{ stat.value }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </UContainer>
        </section>

        <section class="border-t border-white/[0.06]">
            <UContainer class="py-10 lg:py-12">
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
                                <tr class="border-b border-white/[0.06] text-xs text-neutral-600">
                                    <th class="w-[24%] px-6 py-4 font-normal">
                                        Run
                                    </th>
                                    <th class="w-[12%] px-4 py-4 font-normal">
                                        Image
                                    </th>
                                    <th class="w-[8%] px-4 py-4 font-normal">
                                        PHP
                                    </th>
                                    <th class="w-[8%] px-4 py-4 text-right font-normal">
                                        Cores
                                    </th>
                                    <th class="w-[11%] px-4 py-4 text-right font-normal">
                                        Req/s
                                    </th>
                                    <th class="w-[10%] px-4 py-4 text-right font-normal">
                                        p95
                                    </th>
                                    <th class="w-[9%] px-4 py-4 text-right font-normal">
                                        Cost
                                    </th>
                                    <th class="w-[18%] px-6 py-4 font-normal">
                                        Shared by
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visible.length">
                                    <td
                                        colspan="8"
                                        class="px-6 py-16 text-center"
                                    >
                                        <p class="text-sm text-neutral-400">
                                            {{ emptyMessage }}
                                        </p>
                                    </td>
                                </tr>

                                <tr
                                    v-for="(entry, index) in visible"
                                    :key="entry.run_id"
                                    class="group relative transition-colors duration-200 hover:bg-white/[0.03]"
                                    :class="index ? 'border-t border-white/[0.05]' : ''"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1.5">
                                            <!-- after:inset-0 stretches this one link across the
                                                 whole row, which keeps the markup valid. -->
                                            <NuxtLink
                                                :to="`/results/${entry.run_id}`"
                                                class="truncate text-sm text-white after:absolute after:inset-0 after:content-['']"
                                            >
                                                {{ entry.label || entry.provider }}
                                            </NuxtLink>
                                            <UIcon
                                                v-if="entry.verified"
                                                name="i-lucide-badge-check"
                                                class="size-3.5 shrink-0 text-flame-500"
                                                aria-label="Run by a maintainer"
                                            />
                                        </div>
                                        <div class="mt-0.5 truncate text-xs text-neutral-600">
                                            {{ entry.provider }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 font-mono text-xs text-neutral-400">
                                        {{ entry.php_variation }}
                                    </td>
                                    <td class="px-4 py-4 font-mono text-xs text-neutral-400">
                                        {{ entry.php_version }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs text-neutral-400 tabular-nums">
                                        {{ entry.cpu_cores ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono text-sm text-white tabular-nums">
                                            {{ formatNumber(primaryMetric(entry)?.rps) }}
                                        </div>
                                        <!-- primaryMetric falls back JSON, static, DB read, so the
                                             route has to travel with the number to stay honest. -->
                                        <div class="mt-0.5 font-mono text-[10px] text-neutral-600">
                                            {{ primaryMetric(entry)?.label ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs text-neutral-400 tabular-nums">
                                        {{ primaryMetric(entry)?.p95_ms ?? '—' }}<span class="text-neutral-600">ms</span>
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs text-neutral-400 tabular-nums">
                                        {{ monthlyCostLabel(entry.cost_amount, entry.cost_currency) ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="entry.github"
                                                :src="`https://github.com/${entry.github}.png?size=40`"
                                                :alt="entry.github"
                                                class="size-5 shrink-0 rounded-full bg-white/5"
                                                loading="lazy"
                                            >
                                            <span class="truncate text-xs text-neutral-400">
                                                {{ entry.github ? `@${entry.github}` : 'Community' }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 truncate text-xs text-neutral-600">
                                            {{ formatDate(entry.submitted_at) }}
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
                        Hit <span class="text-neutral-200">Submit result</span> in the app. You'll see
                        exactly what gets published, then it opens a pre-filled GitHub issue and a bot
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
                    <p class="mt-8 text-sm text-neutral-600">
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
