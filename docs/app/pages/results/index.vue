<template>
    <UContainer class="mx-auto max-w-[1100px] py-10">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-primary font-semibold text-sm mb-3">
                <UIcon
                    name="i-lucide-users"
                    class="size-4"
                />
                Community results
            </div>
            <h1 class="text-3xl sm:text-4xl font-bold text-highlighted tracking-tight">
                Real-world BenchKit runs
            </h1>
            <p class="mt-3 text-muted max-w-2xl leading-relaxed">
                Runs submitted by the community across different hosts, PHP images, and configurations.
                Filter to compare like-for-like — these are not a single ranking, because a FrankenPHP box
                and a shared vCPU running fpm-nginx aren't the same test.
            </p>
        </div>

        <!-- Honesty banner -->
        <div class="mb-8 flex items-start gap-3 rounded-lg border border-default bg-elevated/50 p-4">
            <UIcon
                name="i-lucide-shield-alert"
                class="size-5 text-warning shrink-0 mt-0.5"
            />
            <div class="text-sm text-muted leading-relaxed">
                <span class="text-highlighted font-medium">Anyone can submit, and each run happens on the submitter's own machine</span>
                — so we can't independently check the numbers. Treat them as real-world data points, not certified scores.
                Every run comes in as a pull request a maintainer reviews before merging, and runs tagged
                <UBadge
                    color="primary"
                    variant="subtle"
                    size="sm"
                    class="mx-1"
                >
                    <UIcon
                        name="i-lucide-badge-check"
                        class="size-3 mr-1"
                    />Maintainer
                </UBadge>
                were run by the BenchKit team.
            </div>
        </div>

        <template v-if="entries.length">
            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-dimmed mr-1">Image</span>
                <UButton
                    v-for="opt in variationOptions"
                    :key="opt.value"
                    :variant="variation === opt.value ? 'solid' : 'outline'"
                    :color="variation === opt.value ? 'primary' : 'neutral'"
                    size="xs"
                    @click="variation = opt.value"
                >
                    {{ opt.label }}
                </UButton>

                <span class="text-xs font-semibold uppercase tracking-wide text-dimmed ml-4 mr-1">Provider</span>
                <UButton
                    v-for="opt in providerOptions"
                    :key="opt"
                    :variant="provider === opt ? 'solid' : 'outline'"
                    :color="provider === opt ? 'primary' : 'neutral'"
                    size="xs"
                    @click="provider = opt"
                >
                    {{ opt === 'all' ? 'All' : opt }}
                </UButton>

                <div class="grow" />

                <!-- Only meaningful while ranking by value, where the currency
                     defines the unit the ratio is expressed in. -->
                <USelect
                    v-if="sortBy === 'value' && currencyOptions.length > 1"
                    :model-value="activeCurrency ?? undefined"
                    :items="currencyOptions"
                    size="xs"
                    class="w-32"
                    @update:model-value="currency = $event"
                />

                <USelect
                    v-model="sortBy"
                    :items="sortOptions"
                    size="xs"
                    class="w-56"
                />
            </div>

            <!-- Distribution strip: the "not a podium" framing -->
            <div
                v-if="filtered.length"
                class="mb-8 rounded-lg border border-default p-5"
            >
                <div class="flex items-baseline justify-between mb-3">
                    <div class="text-sm font-semibold text-highlighted">
                        {{ metricLabel }} across {{ filtered.length }} matching run{{ filtered.length === 1 ? '' : 's' }}
                    </div>
                    <div class="text-xs text-dimmed">
                        median {{ formatNumber(median) }} {{ metricUnit }}
                    </div>
                </div>
                <div class="flex items-end gap-px sm:gap-1 h-24">
                    <div
                        v-for="entry in strip"
                        :key="entry.run_id"
                        class="flex-1 rounded-t bg-primary/70 hover:bg-primary transition-colors relative group cursor-pointer min-w-0"
                        :style="{ height: barHeight(metricValue(entry)) + '%' }"
                        @click="navigateTo(`/results/${entry.run_id}`)"
                    >
                        <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden group-hover:block whitespace-nowrap rounded bg-inverted px-2 py-1 text-xs text-inverted z-10">
                            {{ formatNumber(metricValue(entry)) }} {{ metricUnit }} · {{ entry.provider }}
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-dimmed">
                    <template v-if="strip.length < filtered.length">
                        {{ strip.length }} bars sampled evenly across all {{ filtered.length }} matching runs
                        ({{ metricLabel.toLowerCase() }}){{ stripMatchesSort ? ', tallest to shortest' : '' }}.
                    </template>
                    <template v-else>
                        Each bar is one run ({{ metricLabel.toLowerCase() }}){{ stripMatchesSort ? ', tallest to shortest' : '' }}.
                    </template>
                    It's a spread, not a leaderboard — the hardware and settings behind each run are different.
                </p>
                <p
                    v-if="sortBy === 'value' && activeCurrency"
                    class="mt-2 text-xs text-dimmed"
                >
                    Value ranking compares runs billed in <span class="text-highlighted">{{ activeCurrency }}</span> only.
                    Costs are never converted, so there's no exchange rate here to go out of date; pick another currency
                    to rank within it instead. {{ valueExclusionNote }}
                </p>
            </div>

            <!-- Cards -->
            <div class="grid gap-4 sm:grid-cols-2">
                <NuxtLink
                    v-for="entry in visible"
                    :key="entry.run_id"
                    :to="`/results/${entry.run_id}`"
                    class="group rounded-xl border border-default bg-elevated/40 p-5 hover:border-primary/60 hover:bg-elevated/70 transition-colors"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-highlighted truncate">{{ entry.label }}</h3>
                                <UBadge
                                    v-if="entry.verified"
                                    color="primary"
                                    variant="subtle"
                                    size="sm"
                                >
                                    <UIcon
                                        name="i-lucide-badge-check"
                                        class="size-3 mr-1"
                                    />Maintainer
                                </UBadge>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-dimmed">
                                <UBadge
                                    :color="entry.php_variation === 'frankenphp' ? 'success' : 'neutral'"
                                    variant="subtle"
                                    size="sm"
                                >
                                    {{ entry.php_variation }}
                                </UBadge>
                                <span>PHP {{ entry.php_version }}</span>
                                <span v-if="entry.cpu_cores">·</span>
                                <span v-if="entry.cpu_cores">{{ coresLabel(entry.cpu_cores) }}</span>
                            </div>
                        </div>
                        <UIcon
                            name="i-lucide-arrow-up-right"
                            class="size-4 text-dimmed group-hover:text-primary shrink-0"
                        />
                    </div>

                    <!-- Headline metrics -->
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ formatNumber(primaryMetric(entry)?.rps) }}</div>
                            <div class="text-xs text-dimmed">req/s · {{ primaryMetric(entry)?.label ?? 'HTTP' }}</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ primaryMetric(entry)?.p95_ms ?? '—' }}<span class="text-sm font-normal text-dimmed ml-0.5">ms</span></div>
                            <div class="text-xs text-dimmed">p95 latency</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ entry.php_read_ms ?? '—' }}<span class="text-sm font-normal text-dimmed ml-0.5">ms</span></div>
                            <div class="text-xs text-dimmed">DB read</div>
                        </div>
                    </div>

                    <!-- Footer: submitter + cost -->
                    <div class="mt-4 pt-3 border-t border-default flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <template v-if="entry.github">
                                <img
                                    :src="`https://github.com/${entry.github}.png?size=40`"
                                    :alt="entry.github"
                                    class="size-5 rounded-full bg-elevated"
                                    loading="lazy"
                                >
                                <span class="text-xs text-muted">@{{ entry.github }}</span>
                            </template>
                            <span
                                v-else
                                class="text-xs text-dimmed"
                            >Community submission</span>
                        </div>
                        <div class="text-xs text-dimmed">
                            <template v-if="monthlyCostLabel(entry.cost_amount, entry.cost_currency)">{{ monthlyCostLabel(entry.cost_amount, entry.cost_currency) }} · </template>{{ entry.submitted_at }}
                        </div>
                    </div>
                </NuxtLink>
            </div>

            <div
                v-if="visible.length < filtered.length"
                class="mt-6 text-center"
            >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="shown += PAGE_SIZE"
                >
                    Show more — {{ visible.length }} of {{ filtered.length }}
                </UButton>
            </div>
        </template>

        <!-- Empty state -->
        <div
            v-else
            class="rounded-xl border border-dashed border-default p-10 text-center"
        >
            <UIcon
                name="i-lucide-inbox"
                class="size-8 text-dimmed mx-auto"
            />
            <p class="mt-3 text-muted">
                No community runs yet — be the first to submit one.
            </p>
        </div>

        <!-- Sits outside the CTA below so it's reachable whether or not the
             gallery has runs, and so link crawling finds the page. -->
        <p class="mt-6 text-center text-sm text-muted">
            Already have a submission token?
            <ULink
                to="/results/submit"
                class="text-primary"
            >
                Check it before you submit
            </ULink>
        </p>

        <!-- Submit CTA -->
        <div class="mt-10 rounded-xl border border-primary/30 bg-primary/5 p-6 text-center">
            <h2 class="text-lg font-semibold text-highlighted">
                Ran a benchmark? Add it to the gallery.
            </h2>
            <p class="mt-2 text-sm text-muted max-w-xl mx-auto">
                In the BenchKit app, click <span class="text-highlighted font-medium">Submit result</span> — you'll see
                exactly what gets published, then it opens a pre-filled GitHub issue. A bot files the pull
                request. No hand-editing, and your GitHub username is recorded automatically.
            </p>
            <UButton
                to="https://serversideup.net/open-source/benchkit/docs/getting-started"
                class="mt-4"
                color="primary"
                trailing-icon="i-lucide-arrow-right"
            >
                Run a benchmark
            </UButton>
        </div>
    </UContainer>
</template>

<script setup lang="ts">
import type { ResultsIndex, RunIndex } from '~/types/run'
import { primaryMetric, formatNumber, coresLabel, monthlyCostLabel, valuePerCostUnit } from '~/types/run'

// Summary fields for every run — the whole gallery in one small file. The full
// run documents live one file each and are only loaded when someone opens one,
// so this page's weight tracks the number of runs, not their size.
// URL resolved in setup, not inside the handler — resultsApi reads runtime
// config, and useAsyncData can re-run its handler outside a Nuxt context.
const indexUrl = resultsApi('index.json')
const { data } = await useAsyncData('results-index', () => $fetch<ResultsIndex>(indexUrl))
const entries = computed<RunIndex[]>(() => data.value?.runs ?? [])

const variation = ref<'all' | 'frankenphp' | 'fpm-nginx'>('all')
const provider = ref('all')
const sortBy = ref<'json_rps' | 'static_rps' | 'db_latency' | 'value' | 'newest'>('json_rps')

const variationOptions = [
    { label: 'All', value: 'all' as const },
    { label: 'FrankenPHP', value: 'frankenphp' as const },
    { label: 'fpm-nginx', value: 'fpm-nginx' as const }
]

const providerOptions = computed(() => ['all', ...new Set(entries.value.map(e => e.provider))])

const sortOptions = [
    { label: 'Sort: JSON req/s', value: 'json_rps' },
    { label: 'Sort: Static req/s', value: 'static_rps' },
    { label: 'Sort: DB read latency', value: 'db_latency' },
    { label: 'Sort: req/s per unit cost', value: 'value' },
    { label: 'Sort: Newest', value: 'newest' }
]

// Value ranking is scoped to one currency, because req/s per euro and req/s per
// rupee are different units. Default to whichever currency the most priced runs
// were billed in, so the sort lands on the largest comparable group.
const currencyOptions = computed(() => {
    const counts = new Map<string, number>()

    for (const entry of entries.value) {
        if (valuePerCostUnit(entry) == null || !entry.cost_currency) continue
        counts.set(entry.cost_currency, (counts.get(entry.cost_currency) ?? 0) + 1)
    }

    return [...counts.entries()]
        .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
        .map(([code, count]) => ({ label: `${code} (${count})`, value: code }))
})

const currency = ref<string | null>(null)
const activeCurrency = computed(() => currency.value ?? currencyOptions.value[0]?.value ?? null)

const metricLabel = computed(() => {
    if (sortBy.value === 'static_rps') return 'Static req/s'
    if (sortBy.value === 'value') return `Req/s per 1 ${activeCurrency.value ?? ''}/mo`.trim()
    return 'JSON req/s'
})

const metricUnit = computed(() => sortBy.value === 'value' ? `req/s per ${activeCurrency.value ?? 'unit'}` : 'rps')

function metricValue(entry: RunIndex): number {
    if (sortBy.value === 'static_rps') return entry.static_rps ?? 0
    if (sortBy.value === 'value') return valuePerCostUnit(entry) ?? 0
    return entry.json_rps ?? entry.static_rps ?? 0
}

// The bars are only "tallest to shortest" when the strip's metric is what
// we're sorting by; latency and date orderings say something else.
const stripMatchesSort = computed(() => sortBy.value !== 'db_latency' && sortBy.value !== 'newest')

// Sorting by value hides runs with no price on them — ranking a run at
// "infinite req/s per unit" because nobody typed a cost would be a lie — and
// hides other currencies, whose ratios aren't on the same scale.
const valueSort = computed(() => sortBy.value === 'value')

const filtered = computed(() => {
    const list = entries.value.filter((e) => {
        if (variation.value !== 'all' && e.php_variation !== variation.value) return false
        if (provider.value !== 'all' && e.provider !== provider.value) return false
        if (valueSort.value && valuePerCostUnit(e) == null) return false
        if (valueSort.value && e.cost_currency !== activeCurrency.value) return false
        return true
    })
    return [...list].sort((a, b) => {
        if (sortBy.value === 'db_latency') {
            return (a.db_read_p95_ms ?? Infinity) - (b.db_read_p95_ms ?? Infinity)
        }
        if (sortBy.value === 'newest') return b.submitted_at.localeCompare(a.submitted_at)
        return metricValue(b) - metricValue(a)
    })
})

// How many runs the value sort is leaving out, split by reason, so the note
// under the strip can be specific rather than just "some runs are hidden".
const excludedFromValue = computed(() => {
    let unpriced = 0
    let otherCurrency = 0

    for (const entry of entries.value) {
        if (valuePerCostUnit(entry) == null) unpriced++
        else if (entry.cost_currency !== activeCurrency.value) otherCurrency++
    }

    return { unpriced, otherCurrency }
})

// Built here rather than as nested <template> fragments, where the linter's
// newline rules turn "a, b." into "a , b ."
const valueExclusionNote = computed(() => {
    const { unpriced, otherCurrency } = excludedFromValue.value
    const parts: string[] = []

    if (unpriced) parts.push(`${unpriced} with no cost recorded`)
    if (otherCurrency) parts.push(`${otherCurrency} billed in another currency`)

    return parts.length ? `Hidden: ${parts.join(', ')}.` : ''
})

// Cards are paged rather than dumped: at a few thousand runs the grid alone is
// tens of thousands of DOM nodes, and nobody scrolls past the first screen
// anyway.
const PAGE_SIZE = 24
const shown = ref(PAGE_SIZE)
const visible = computed(() => filtered.value.slice(0, shown.value))
watch([variation, provider, sortBy, activeCurrency], () => shown.value = PAGE_SIZE)

// One bar per run stops being a distribution once the bars are thinner than
// the gaps between them, so past this many we sample evenly across the sorted
// list — same shape, readable width — and say so underneath.
const MAX_BARS = 120
const strip = computed(() => {
    const list = filtered.value
    if (list.length <= MAX_BARS) return list

    const step = list.length / MAX_BARS
    return Array.from({ length: MAX_BARS }, (_, i) => list[Math.floor(i * step)]!)
})

const median = computed(() => {
    const vals = filtered.value.map(metricValue).filter(v => v > 0).sort((a, b) => a - b)
    if (!vals.length) return 0
    const mid = Math.floor(vals.length / 2)
    return vals.length % 2 ? vals[mid]! : (vals[mid - 1]! + vals[mid]!) / 2
})

// reduce, not Math.max(...spread) — spreading an array into a call throws
// RangeError past ~130k arguments, which is a crash rather than a slow page.
const maxMetric = computed(() => filtered.value.reduce((max, entry) => Math.max(max, metricValue(entry)), 1))
function barHeight(v: number): number {
    return Math.max(8, Math.round((v / maxMetric.value) * 100))
}

useSeoMeta({
    title: 'Community results',
    description: 'Real-world BenchKit runs submitted by the community across hosts, PHP images, and configurations.'
})
</script>
