<template>
    <UContainer class="mx-auto max-w-[1100px] py-10">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-primary font-semibold text-sm mb-3">
                <UIcon name="i-lucide-users" class="size-4" />
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
            <UIcon name="i-lucide-shield-alert" class="size-5 text-warning shrink-0 mt-0.5" />
            <div class="text-sm text-muted leading-relaxed">
                <span class="text-highlighted font-medium">Anyone can submit, and each run happens on the submitter's own machine</span>
                — so we can't independently check the numbers. Treat them as real-world data points, not certified scores.
                Every run comes in as a pull request a maintainer reviews before merging, and runs tagged
                <UBadge color="primary" variant="subtle" size="sm" class="mx-1"><UIcon name="i-lucide-badge-check" class="size-3 mr-1" />Maintainer</UBadge>
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

                <USelect v-model="sortBy" :items="sortOptions" size="xs" class="w-52" />
            </div>

            <!-- Distribution strip: the "not a podium" framing -->
            <div v-if="filtered.length" class="mb-8 rounded-lg border border-default p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div class="text-sm font-semibold text-highlighted">
                        {{ metricLabel }} across {{ filtered.length }} matching run{{ filtered.length === 1 ? '' : 's' }}
                    </div>
                    <div class="text-xs text-dimmed">median {{ formatNumber(median) }} rps</div>
                </div>
                <div class="flex items-end gap-1.5 h-24">
                    <div
                        v-for="entry in filtered"
                        :key="entry.run.id"
                        class="flex-1 rounded-t bg-primary/70 hover:bg-primary transition-colors relative group cursor-pointer min-w-0"
                        :style="{ height: barHeight(metricValue(entry)) + '%' }"
                        @click="navigateTo(`/results/${entry.run.id}`)"
                    >
                        <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden group-hover:block whitespace-nowrap rounded bg-inverted px-2 py-1 text-xs text-inverted z-10">
                            {{ formatNumber(metricValue(entry)) }} rps · {{ entry.run.meta.provider || 'Self-hosted' }}
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-dimmed">
                    Each bar is one run ({{ metricLabel.toLowerCase() }}), tallest to shortest. It's a spread, not a
                    leaderboard — the hardware and settings behind each run are different.
                </p>
            </div>

            <!-- Cards -->
            <div class="grid gap-4 sm:grid-cols-2">
                <NuxtLink
                    v-for="entry in filtered"
                    :key="entry.run.id"
                    :to="`/results/${entry.run.id}`"
                    class="group rounded-xl border border-default bg-elevated/40 p-5 hover:border-primary/60 hover:bg-elevated/70 transition-colors"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-highlighted truncate">{{ entry.run.meta.label }}</h3>
                                <UBadge v-if="entry.submission.verified" color="primary" variant="subtle" size="sm">
                                    <UIcon name="i-lucide-badge-check" class="size-3 mr-1" />Maintainer
                                </UBadge>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-dimmed">
                                <UBadge :color="entry.run.environment.php.php_variation === 'frankenphp' ? 'success' : 'neutral'" variant="subtle" size="sm">
                                    {{ entry.run.environment.php.php_variation }}
                                </UBadge>
                                <span>PHP {{ entry.run.environment.php.php_version }}</span>
                                <span>·</span>
                                <span>{{ coresLabel(entry.run.environment.server.cpu_cores) }}</span>
                            </div>
                        </div>
                        <UIcon name="i-lucide-arrow-up-right" class="size-4 text-dimmed group-hover:text-primary shrink-0" />
                    </div>

                    <!-- Headline metrics -->
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ formatNumber(primaryRoute(entry)?.requests_per_second) }}</div>
                            <div class="text-xs text-dimmed">req/s · {{ primaryRouteLabel(entry) }}</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ primaryRoute(entry)?.p95_ms ?? '—' }}<span class="text-sm font-normal text-dimmed ml-0.5">ms</span></div>
                            <div class="text-xs text-dimmed">p95 latency</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-highlighted tabular-nums">{{ entry.run.benchmarks.php?.headline?.read?.milliseconds ?? '—' }}<span class="text-sm font-normal text-dimmed ml-0.5">ms</span></div>
                            <div class="text-xs text-dimmed">DB read</div>
                        </div>
                    </div>

                    <!-- Footer: submitter + cost -->
                    <div class="mt-4 pt-3 border-t border-default flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <template v-if="entry.submission.github">
                                <img
                                    :src="`https://github.com/${entry.submission.github}.png?size=40`"
                                    :alt="entry.submission.github"
                                    class="size-5 rounded-full bg-elevated"
                                    loading="lazy"
                                >
                                <span class="text-xs text-muted">@{{ entry.submission.github }}</span>
                            </template>
                            <span v-else class="text-xs text-dimmed">Community submission</span>
                        </div>
                        <div class="text-xs text-dimmed">
                            <template v-if="costLabel(entry.run.meta.cost)">{{ costLabel(entry.run.meta.cost) }} · </template>{{ entry.submission.submitted_at }}
                        </div>
                    </div>
                </NuxtLink>
            </div>
        </template>

        <!-- Empty state -->
        <div v-else class="rounded-xl border border-dashed border-default p-10 text-center">
            <UIcon name="i-lucide-inbox" class="size-8 text-dimmed mx-auto" />
            <p class="mt-3 text-muted">No community runs yet — be the first to submit one.</p>
        </div>

        <!-- Submit CTA -->
        <div class="mt-10 rounded-xl border border-primary/30 bg-primary/5 p-6 text-center">
            <h2 class="text-lg font-semibold text-highlighted">Ran a benchmark? Add it to the gallery.</h2>
            <p class="mt-2 text-sm text-muted max-w-xl mx-auto">
                In the BenchKit app, click <span class="text-highlighted font-medium">Submit Results</span> — it opens a
                pre-filled pull request with your run's JSON. No hand-editing. Your GitHub username is
                recorded automatically from the PR.
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
import type { RunEntry } from '~/types/run'
import { primaryRoute, formatNumber, coresLabel, costLabel } from '~/types/run'

const { data } = await useAsyncData('runs', () => queryCollection('runs').all())
const entries = computed<RunEntry[]>(() => (data.value ?? []) as unknown as RunEntry[])

const variation = ref<'all' | 'frankenphp' | 'fpm-nginx'>('all')
const provider = ref('all')
const sortBy = ref<'json_rps' | 'static_rps' | 'db_latency' | 'newest'>('json_rps')

const variationOptions = [
    { label: 'All', value: 'all' as const },
    { label: 'FrankenPHP', value: 'frankenphp' as const },
    { label: 'fpm-nginx', value: 'fpm-nginx' as const }
]

const providerOptions = computed(() => ['all', ...new Set(entries.value.map(e => e.run.meta.provider || 'Self-hosted'))])

const sortOptions = [
    { label: 'Sort: JSON req/s', value: 'json_rps' },
    { label: 'Sort: Static req/s', value: 'static_rps' },
    { label: 'Sort: DB read latency', value: 'db_latency' },
    { label: 'Sort: Newest', value: 'newest' }
]

const metricLabel = computed(() => sortBy.value === 'static_rps' ? 'Static req/s' : 'JSON req/s')

function metricValue(entry: RunEntry): number {
    const routes = entry.run.benchmarks.http?.routes
    if (sortBy.value === 'static_rps') return routes?.static?.requests_per_second ?? 0
    return routes?.json?.requests_per_second ?? routes?.static?.requests_per_second ?? 0
}

function primaryRouteLabel(entry: RunEntry): string {
    const r = entry.run.benchmarks.http?.routes
    if (r?.json) return 'JSON'
    if (r?.static) return 'static'
    if (r?.db_read) return 'DB read'
    return 'HTTP'
}

const filtered = computed(() => {
    const list = entries.value.filter((e) => {
        if (variation.value !== 'all' && e.run.environment.php.php_variation !== variation.value) return false
        if (provider.value !== 'all' && (e.run.meta.provider || 'Self-hosted') !== provider.value) return false
        return true
    })
    return [...list].sort((a, b) => {
        if (sortBy.value === 'db_latency') {
            return (a.run.benchmarks.http?.routes?.db_read?.p95_ms ?? Infinity) - (b.run.benchmarks.http?.routes?.db_read?.p95_ms ?? Infinity)
        }
        if (sortBy.value === 'newest') return b.submission.submitted_at.localeCompare(a.submission.submitted_at)
        return metricValue(b) - metricValue(a)
    })
})

const median = computed(() => {
    const vals = filtered.value.map(metricValue).filter(v => v > 0).sort((a, b) => a - b)
    if (!vals.length) return 0
    const mid = Math.floor(vals.length / 2)
    return vals.length % 2 ? vals[mid]! : (vals[mid - 1]! + vals[mid]!) / 2
})

const maxMetric = computed(() => Math.max(1, ...filtered.value.map(metricValue)))
function barHeight(v: number): number {
    return Math.max(8, Math.round((v / maxMetric.value) * 100))
}

useSeoMeta({
    title: 'Community results',
    description: 'Real-world BenchKit runs submitted by the community across hosts, PHP images, and configurations.'
})
</script>
