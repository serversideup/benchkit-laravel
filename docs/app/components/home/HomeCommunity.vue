<script setup lang="ts">
import type { ResultsIndex, RunIndex } from '~/types/run'
import { primaryMetric, formatNumber, monthlyCostLabel } from '~/types/run'

const SHOWN = 6

const indexUrl = resultsApi('index.json')

const { data } = await useAsyncData('home-community', () => $fetch<ResultsIndex>(indexUrl))

const entries = computed<RunIndex[]>(() => data.value?.runs ?? [])
const total = computed(() => entries.value.length)

/**
 * Deliberately no "best value" sort here. The gallery ranks value within a
 * single currency, because req/s per euro and req/s per rupee aren't the same
 * unit, and that caveat needs more room than a home page gives it.
 */
const SORTS = [
    { key: 'latest', label: 'Latest' },
    { key: 'fastest', label: 'Fastest' },
    { key: 'latency', label: 'Lowest latency' }
] as const

const sort = ref<typeof SORTS[number]['key']>('latest')

/** `verified` means a maintainer ran it themselves, not that a bot checked it. */
const verifiedOnly = ref(false)

const filtered = computed(() => verifiedOnly.value
    ? entries.value.filter(entry => entry.verified)
    : entries.value)

const sorted = computed(() => [...filtered.value].sort((a, b) => {
    if (sort.value === 'fastest') {
        return (primaryMetric(b)?.rps ?? 0) - (primaryMetric(a)?.rps ?? 0)
    }

    if (sort.value === 'latency') {
        return (primaryMetric(a)?.p95_ms ?? Infinity) - (primaryMetric(b)?.p95_ms ?? Infinity)
    }

    return b.submitted_at.localeCompare(a.submitted_at) || b.run_id.localeCompare(a.run_id)
}).slice(0, SHOWN))
</script>

<template>
    <section class="border-t border-white/[0.06]">
        <UContainer class="py-20 lg:py-28">
            <HomeReveal>
                <HomeSectionHeading
                    eyebrow="Community"
                    centered
                >
                    <template #title>
                        <span class="block">See what everyone else is running.</span>
                    </template>
                    <template #description>
                        Every submission carries the machine, the configuration, and the numbers.
                    </template>
                </HomeSectionHeading>
            </HomeReveal>

            <HomeReveal :delay="120">
                <div
                    v-if="total"
                    class="relative mt-12 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02]"
                >
                    <!-- Hairline along the top edge -->
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-x-32 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                    />

                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.08] p-2 pl-4">
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="option in SORTS"
                                :key="option.key"
                                type="button"
                                :aria-pressed="sort === option.key"
                                class="cursor-pointer rounded-lg px-3 py-2 text-sm transition-colors duration-200"
                                :class="sort === option.key
                                    ? 'bg-flame-500/15 text-flame-400'
                                    : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                                @click="sort = option.key"
                            >
                                {{ option.label }}
                            </button>
                        </div>

                        <div class="flex items-center gap-3 pr-3">
                            <button
                                type="button"
                                :aria-pressed="verifiedOnly"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-2 text-sm transition-colors duration-200"
                                :class="verifiedOnly
                                    ? 'bg-flame-500/15 text-flame-400'
                                    : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                                @click="verifiedOnly = !verifiedOnly"
                            >
                                <UIcon
                                    name="i-lucide-badge-check"
                                    class="size-3.5"
                                />
                                Verified
                            </button>

                            <span class="text-xs text-neutral-600">
                                {{ verifiedOnly ? `${filtered.length} verified` : `${total} shared` }}
                            </span>
                        </div>
                    </div>

                    <!-- Scrolls inside its own container, so a narrow screen never
                         makes the page itself scroll sideways. -->
                    <div class="overflow-x-auto">
                        <!-- table-fixed with explicit widths: left to itself the
                             browser spreads eight short columns across the full
                             container and they stop reading as a row. -->
                        <table class="w-full min-w-[52rem] table-fixed text-left">
                            <thead>
                                <tr class="border-b border-white/[0.06] text-xs text-neutral-600">
                                    <th class="w-[26%] px-6 py-3 font-normal">
                                        Run
                                    </th>
                                    <th class="w-[13%] px-4 py-3 font-normal">
                                        Image
                                    </th>
                                    <th class="w-[9%] px-4 py-3 font-normal">
                                        PHP
                                    </th>
                                    <th class="w-[9%] px-4 py-3 text-right font-normal">
                                        Cores
                                    </th>
                                    <th class="w-[10%] px-4 py-3 text-right font-normal">
                                        Req/s
                                    </th>
                                    <th class="w-[11%] px-4 py-3 text-right font-normal">
                                        p95
                                    </th>
                                    <th class="w-[9%] px-4 py-3 text-right font-normal">
                                        Cost
                                    </th>
                                    <th class="w-[13%] px-6 py-3 font-normal">
                                        Shared by
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!sorted.length">
                                    <td
                                        colspan="8"
                                        class="px-6 py-10 text-center text-sm text-neutral-500"
                                    >
                                        No maintainer runs shared yet.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(entry, index) in sorted"
                                    :key="entry.run_id"
                                    class="group relative transition-colors duration-200 hover:bg-white/[0.03]"
                                    :class="index ? 'border-t border-white/[0.05]' : ''"
                                >
                                    <td class="px-6 py-4">
                                        <!-- after:inset-0 stretches this one link across the
                                             whole row, which keeps the markup valid. -->
                                        <div class="flex items-center gap-1.5">
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
                                    <td class="px-4 py-4">
                                        <span class="font-mono text-xs text-neutral-400">{{ entry.php_variation }}</span>
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Nothing shared yet: an invitation reads better than an empty frame. -->
                <div
                    v-else
                    class="mt-12 rounded-2xl border border-dashed border-white/10 p-10 text-center"
                >
                    <p class="text-sm text-neutral-400">
                        No runs shared yet. Yours could be the first one here.
                    </p>
                </div>
            </HomeReveal>

            <HomeReveal :delay="180">
                <div class="mt-8 flex flex-col items-center gap-4">
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <UButton
                            to="/results"
                            size="xl"
                            color="primary"
                            trailing-icon="i-lucide-arrow-right"
                        >
                            Browse all results
                        </UButton>

                        <UButton
                            to="/docs/community-results"
                            size="xl"
                            color="neutral"
                            variant="outline"
                        >
                            Share yours
                        </UButton>
                    </div>

                    <p class="text-center text-sm text-neutral-600">
                        Different hardware and settings, so compare like with like.
                    </p>
                </div>
            </HomeReveal>
        </UContainer>
    </section>
</template>
