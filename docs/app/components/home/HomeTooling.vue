<script setup lang="ts">
/**
 * The stages in the order a run executes them. Each card credits the project
 * that actually wrote the tool: the serversideup packages are Composer
 * distributions of someone else's work, not our own implementations.
 */
const TOOLS = [
    {
        name: 'YABS',
        description: 'Runs Geekbench 6, fio, and iperf3 against the box.',
        repo: 'masonr/yet-another-bench-script',
        url: 'https://github.com/masonr/yet-another-bench-script'
    },
    {
        name: 'cfspeedtest',
        description: 'Latency, download, and upload against Cloudflare\'s edge.',
        repo: 'code-inflation/cfspeedtest',
        url: 'https://github.com/code-inflation/cfspeedtest'
    },
    {
        name: 'oha',
        description: 'Sustained load against the app\'s own routes.',
        repo: 'hatoo/oha',
        url: 'https://github.com/hatoo/oha'
    },
    {
        name: 'phpbench',
        description: 'Times Eloquent CRUD and the PHP work underneath it.',
        repo: 'phpbench/phpbench',
        url: 'https://github.com/phpbench/phpbench'
    }
]
</script>

<template>
    <section class="border-t border-white/[0.06]">
        <UContainer class="py-20 lg:py-28">
            <HomeReveal>
                <HomeSectionHeading eyebrow="The tooling">
                    <template #title>
                        <span class="block">We didn't invent a score.</span>
                        <span class="block text-neutral-500">We run the tools you already trust.</span>
                    </template>
                    <template #description>
                        BenchKit runs community-trusted tools against your host to help you
                        quantify how it performs.
                    </template>
                </HomeSectionHeading>
            </HomeReveal>

            <div class="mt-14 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <HomeReveal
                    v-for="(tool, index) in TOOLS"
                    :key="tool.name"
                    :delay="index * 90"
                    class="h-full"
                >
                    <a
                        :href="tool.url"
                        target="_blank"
                        rel="noopener"
                        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02] p-6 transition-[border-color,transform] duration-200 hover:-translate-y-0.5 hover:border-white/20"
                    >
                        <!-- Hairline along the top edge, warming to brand on hover. -->
                        <div
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent transition-colors duration-300 group-hover:via-flame-500/60"
                        />

                        <!-- The index makes "runs them in order" literal. -->
                        <span class="font-mono text-[11px] text-neutral-700 tabular-nums transition-colors duration-200 group-hover:text-neutral-500">
                            {{ String(index + 1).padStart(2, '0') }}
                        </span>

                        <h3 class="mt-5 font-sans text-lg font-semibold tracking-tight text-white">
                            {{ tool.name }}
                        </h3>

                        <p class="mt-2 text-pretty text-sm leading-relaxed text-neutral-400">
                            {{ tool.description }}
                        </p>

                        <!-- mt-auto pins the credit line to the bottom, so it lines
                             up across cards of different copy lengths. -->
                        <span class="mt-auto flex items-center gap-1.5 pt-6 font-mono text-[11px] text-neutral-500 transition-colors duration-200 group-hover:text-flame-400">
                            <span class="truncate">{{ tool.repo }}</span>
                            <UIcon
                                name="i-lucide-arrow-up-right"
                                class="size-3.5 shrink-0"
                            />
                        </span>
                    </a>
                </HomeReveal>
            </div>

            <HomeReveal :delay="120">
                <p class="mt-8 text-pretty text-sm leading-relaxed text-neutral-500">
                    All tools ship through Composer, so setup and teardown are a breeze.
                </p>
            </HomeReveal>
        </UContainer>
    </section>
</template>
