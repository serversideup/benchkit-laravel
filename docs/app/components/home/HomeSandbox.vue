<script setup lang="ts">
/**
 * The published image variations. scripts/configure-php-versions.sh sets
 * fpm-nginx as the default tag and drops only `cli` and `fpm` from the
 * serversideup/php set, so these three are what you can actually pull.
 *
 * FrankenPHP carries the octane:start command because worker mode is the
 * reason to reach for it.
 */
const VARIATIONS = [
    {
        tag: 'fpm-nginx',
        why: 'The classic stack, and what most Laravel apps run today.',
        command: `docker run -p 80:8080 \\
  -v benchkit-runs:/var/www/html/storage/app/runs \\
  serversideup/benchkit-laravel`
    },
    {
        tag: 'frankenphp',
        why: 'Worker mode. Your app boots once and stays in memory.',
        command: `docker run -p 80:8080 \\
  -v benchkit-runs:/var/www/html/storage/app/runs \\
  serversideup/benchkit-laravel:frankenphp \\
  php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080`
    },
    {
        tag: 'fpm-apache',
        why: 'The same PHP-FPM, served by Apache instead.',
        command: `docker run -p 80:8080 \\
  -v benchkit-runs:/var/www/html/storage/app/runs \\
  serversideup/benchkit-laravel:fpm-apache`
    }
]

const active = ref(0)
const selected = computed(() => VARIATIONS[active.value]!)

// legacy: falls back to execCommand where navigator.clipboard is unavailable,
// which includes any non-secure context (a plain-HTTP host or IP).
const { copy, copied } = useClipboard({ copiedDuring: 2000, legacy: true })
</script>

<template>
    <section class="border-t border-white/[0.06]">
        <UContainer class="py-20 lg:py-28">
            <!-- Two columns from xl, matching the workload section: the panel
                 alone against the left edge left half the viewport empty. -->
            <div class="grid gap-12 xl:grid-cols-[1fr_1.3fr] xl:items-center xl:gap-20">
                <HomeReveal>
                    <HomeSectionHeading eyebrow="Swap the stack">
                        <template #title>
                            <span class="block">Change the server.</span>
                            <span class="block text-neutral-500">Run it again.</span>
                        </template>
                        <template #description>
                            Same app, same workload, different runtime. The only thing that moved
                            is the thing you wanted to test.
                        </template>
                    </HomeSectionHeading>

                    <p class="mt-8 max-w-md text-pretty text-sm leading-relaxed text-neutral-500">
                        Not a Docker shop? Clone the repo and run it on bare metal, a VPS, or
                        Laravel Cloud. It's a normal Laravel app.
                    </p>
                </HomeReveal>

                <HomeReveal :delay="120">
                    <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02]">
                        <!-- Hairline along the top edge -->
                        <div
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-x-16 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                        />

                        <div
                            role="tablist"
                            aria-label="Image variation"
                            class="flex flex-wrap gap-1 border-b border-white/[0.08] p-2"
                        >
                            <button
                                v-for="(variation, index) in VARIATIONS"
                                :key="variation.tag"
                                type="button"
                                role="tab"
                                :aria-selected="index === active"
                                class="cursor-pointer rounded-lg px-3 py-2 font-mono text-xs transition-colors duration-200"
                                :class="index === active
                                    ? 'bg-flame-500/15 text-flame-400'
                                    : 'text-neutral-500 hover:bg-white/[0.04] hover:text-neutral-300'"
                                @click="active = index"
                            >
                                {{ variation.tag }}
                            </button>
                        </div>

                        <Transition
                            enter-active-class="transition-opacity duration-200"
                            leave-active-class="transition-opacity duration-150"
                            enter-from-class="opacity-0"
                            leave-to-class="opacity-0"
                            mode="out-in"
                        >
                            <div :key="selected.tag">
                                <p class="px-6 pt-6 text-sm text-neutral-400">
                                    {{ selected.why }}
                                </p>

                                <!-- min-h holds the frame steady across variations, so
                                 switching tabs fades rather than jumps. -->
                                <div class="relative min-h-[7.5rem] px-6 pb-6 pt-4">
                                    <pre class="overflow-x-auto pr-12 font-mono text-xs leading-relaxed text-neutral-300"><code>{{ selected.command }}</code></pre>

                                    <button
                                        type="button"
                                        :aria-label="`Copy the ${selected.tag} command`"
                                        class="absolute right-5 top-3 cursor-pointer rounded-lg border border-white/10 bg-white/[0.04] p-2 text-neutral-400 transition-colors duration-200 hover:border-white/20 hover:text-white"
                                        @click="copy(selected.command)"
                                    >
                                        <UIcon
                                            :name="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                                            class="size-4"
                                            :class="copied ? 'text-flame-400' : ''"
                                        />
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </HomeReveal>
            </div>
        </UContainer>
    </section>
</template>
