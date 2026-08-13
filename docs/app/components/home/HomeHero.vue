<template>
    <section class="relative overflow-hidden">
        <!-- Default container, so the hero's left edge lines up with the
             header logo. Two columns only from xl, where the copy column is
             wide enough to hold the headline on two lines. -->
        <UContainer class="pb-12 pt-16 lg:pb-16 lg:pt-20">
            <div class="grid items-center gap-14 xl:grid-cols-[1.4fr_1fr] xl:gap-20">
                <div>
                    <div class="enter inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] py-1 pl-2.5 pr-3">
                        <span
                            aria-hidden="true"
                            class="size-1.5 rounded-full bg-flame-500"
                        />
                        <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-400">
                            Free and open source
                        </span>
                    </div>

                    <h1
                        class="enter mt-6 text-balance text-4xl font-semibold leading-[1.06] tracking-[-0.03em] text-white sm:text-5xl"
                        style="animation-delay: 40ms"
                    >
                        <span class="block">Benchmark your Laravel stack.</span>
                        <span class="block text-neutral-500">Learn from everyone else's.</span>
                    </h1>

                    <p
                        class="enter mt-5 max-w-xl text-pretty text-lg leading-relaxed text-neutral-400"
                        style="animation-delay: 80ms"
                    >
                        BenchKit measures what your host, your hardware, and your PHP configuration
                        are really doing. Run it on the box you care about, then share what you
                        learn with everyone else.
                    </p>

                    <div
                        class="enter mt-8 flex flex-wrap items-center gap-3"
                        style="animation-delay: 120ms"
                    >
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
                            to="/results"
                            size="xl"
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-gauge"
                            class="w-full justify-center sm:w-auto"
                        >
                            Browse community results
                        </UButton>
                    </div>

                    <div
                        class="enter mt-9"
                        style="animation-delay: 160ms"
                    >
                        <p class="max-w-xl text-pretty text-sm leading-relaxed text-neutral-500">
                            Ships as FrankenPHP, FPM-NGINX, or FPM-Apache, built on serversideup/php
                            so every setting is yours to change.
                        </p>
                    </div>
                </div>

                <div
                    class="enter"
                    style="animation-delay: 100ms"
                >
                    <HomeSharedRun />
                </div>
            </div>
        </UContainer>
    </section>
</template>
