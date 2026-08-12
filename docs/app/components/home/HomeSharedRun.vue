<script setup lang="ts">
import type { ResultsIndex, RunEntry } from '~/types/run'

/**
 * The hero cycles the newest submissions rather than featuring the fastest one.
 * BenchKit is a place to learn from other people's setups, not a leaderboard,
 * and showing several makes the point that these are different machines running
 * different configurations, not entries in a ranking.
 *
 * resultsApi reads runtime config, so the prefix is resolved here in setup —
 * the handler below runs outside the Nuxt context after its first await.
 */
const FEATURED_COUNT = 4

const indexUrl = resultsApi('index.json')
const apiRoot = resultsApi('')

const { data: runs } = await useAsyncData('home-shared-runs', async () => {
    const index = await $fetch<ResultsIndex>(indexUrl)

    const newest = [...(index.runs ?? [])]
        .sort((a, b) => b.submitted_at.localeCompare(a.submitted_at) || b.run_id.localeCompare(a.run_id))
        .slice(0, FEATURED_COUNT)

    // The summary index carries no OS, RAM, plan, or per-route detail, and
    // that's what makes a card read like somebody's actual machine.
    return Promise.all(newest.map(async (summary) => {
        const detail = await $fetch<RunEntry>(`${apiRoot}${summary.run_id}.json`)

        return { summary, run: detail.run }
    }))
}, { default: () => [] })

const active = ref(0)
const paused = ref(false)

/**
 * Auto-advance is a nicety, so it only ever starts on the client, never runs
 * for a single run, and stays off entirely for anyone who asked for less
 * motion. Hovering or tabbing into the card holds the current one.
 */
onMounted(() => {
    if (runs.value.length < 2) return
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const timer = setInterval(() => {
        if (!paused.value) active.value = (active.value + 1) % runs.value.length
    }, 6000)

    onBeforeUnmount(() => clearInterval(timer))
})
</script>

<template>
    <div class="relative">
        <!-- Ambient warmth, so the card reads as lit rather than pasted on. -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -inset-20 -z-10 blur-3xl"
            style="background: radial-gradient(42% 42% at 62% 34%, rgba(230, 46, 5, 0.20), transparent 72%)"
        />

        <template v-if="runs.length">
            <!-- Every card shares one grid cell, so the frame is as tall as the
                 tallest run and nothing jumps as they cross-fade. -->
            <div
                class="grid"
                @mouseenter="paused = true"
                @mouseleave="paused = false"
                @focusin="paused = true"
                @focusout="paused = false"
            >
                <div
                    v-for="(entry, index) in runs"
                    :key="entry.summary.run_id"
                    :inert="index !== active"
                    class="col-start-1 row-start-1 transition-opacity duration-500"
                    :class="index === active ? 'opacity-100' : 'opacity-0'"
                >
                    <HomeRunCard
                        :summary="entry.summary"
                        :run="entry.run"
                    />
                </div>
            </div>

            <div
                v-if="runs.length > 1"
                class="mt-5 flex items-center justify-center gap-2"
            >
                <button
                    v-for="(entry, index) in runs"
                    :key="entry.summary.run_id"
                    type="button"
                    :aria-label="`Show run ${index + 1} of ${runs.length}`"
                    :aria-current="index === active"
                    class="h-1.5 cursor-pointer rounded-full transition-all duration-300"
                    :class="index === active ? 'w-6 bg-flame-500' : 'w-1.5 bg-white/20 hover:bg-white/40'"
                    @click="active = index"
                />
            </div>
        </template>

        <!-- Nothing shared yet: an invitation reads better than an empty frame. -->
        <div
            v-else
            class="rounded-2xl border border-dashed border-white/10 p-10 text-center"
        >
            <p class="text-sm text-neutral-400">
                No runs shared yet. Yours could be the first one here.
            </p>
        </div>
    </div>
</template>
