<template>
    <!-- No eyebrow here, unlike every section above: its absence is part of how
         this reads as the end of the page rather than one more section. -->
    <section class="relative overflow-hidden border-t border-white/[0.06]">
        <!-- The page's second and last ambient glow, bookending the hero. -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-96 blur-3xl"
            style="background: radial-gradient(40% 60% at 50% 0%, rgba(230, 46, 5, 0.18), transparent 70%)"
        />

        <UContainer class="relative py-24 lg:py-32">
            <HomeReveal>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-balance text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-white sm:text-5xl">
                        Your turn.
                    </h2>

                    <p class="mt-5 text-pretty text-lg leading-relaxed text-neutral-400">
                        Run it on the box you're curious about. It's free, open source, and one
                        command away.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                        <UButton
                            to="/docs/getting-started"
                            size="xl"
                            color="primary"
                            trailing-icon="i-lucide-arrow-right"
                            class="w-full justify-center sm:w-auto"
                        >
                            Run your first benchmark
                        </UButton>

                        <UButton
                            to="https://github.com/serversideup/benchkit-laravel"
                            target="_blank"
                            size="xl"
                            color="neutral"
                            variant="outline"
                            icon="i-simple-icons-github"
                            class="w-full justify-center sm:w-auto"
                        >
                            Star on GitHub
                        </UButton>
                    </div>
                </div>
            </HomeReveal>
        </UContainer>
    </section>
</template>
