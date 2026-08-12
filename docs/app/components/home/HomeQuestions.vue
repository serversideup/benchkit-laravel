<script setup lang="ts">
/**
 * The misconceptions that would stop someone before they try it. Answers track
 * content/docs/6.faq, so the two can't drift apart. Database swapping and
 * result sharing are deliberately absent: earlier sections already cover them.
 */
const QUESTIONS = [
    {
        question: 'Is this a Laravel package I install?',
        answer: 'No. BenchKit is a standalone app you spin up, benchmark with, and then throw away. It never goes near your own codebase.'
    },
    {
        question: 'Do I need Docker?',
        answer: 'No. Clone the repo and run it anywhere Laravel runs, including Laravel Cloud. The benchmark tools arrive with composer install.'
    },
    {
        question: 'Can I compare two runs?',
        answer: 'Yes. Run it once on each setup and open them side by side to see exactly what your change did.'
    },
    {
        question: 'Do my results survive a rebuild?',
        answer: 'Mount a volume at storage/app/runs and they do. Without one, your history lasts as long as the container.'
    }
]
</script>

<template>
    <section class="border-t border-white/[0.06]">
        <UContainer class="py-20 lg:py-28">
            <HomeReveal>
                <HomeSectionHeading
                    eyebrow="Questions"
                    centered
                >
                    <template #title>
                        <span class="block">A few common questions.</span>
                    </template>
                </HomeSectionHeading>
            </HomeReveal>

            <dl class="mx-auto mt-12 max-w-4xl">
                <HomeReveal
                    v-for="(item, index) in QUESTIONS"
                    :key="item.question"
                    :delay="index * 80"
                >
                    <div
                        class="grid gap-2 py-6 md:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] md:gap-10"
                        :class="index ? 'border-t border-white/[0.06]' : ''"
                    >
                        <dt class="text-balance font-sans text-base font-semibold text-white">
                            {{ item.question }}
                        </dt>
                        <dd class="text-pretty leading-relaxed text-neutral-400">
                            {{ item.answer }}
                        </dd>
                    </div>
                </HomeReveal>
            </dl>

            <HomeReveal :delay="160">
                <!-- Two standalone links rather than a sentence: the linter's
                     newline rules put a space before punctuation that follows a
                     multi-line tag, turning "FAQ, or" into "FAQ , or". -->
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4 text-sm">
                    <ULink
                        to="/docs/faq"
                        class="text-neutral-400 underline decoration-white/20 underline-offset-4 transition-colors hover:text-flame-400"
                    >
                        Read the full FAQ
                    </ULink>
                    <span
                        aria-hidden="true"
                        class="text-neutral-700"
                    >·</span>
                    <ULink
                        to="https://serversideup.net/discord"
                        target="_blank"
                        class="text-neutral-400 underline decoration-white/20 underline-offset-4 transition-colors hover:text-flame-400"
                    >
                        Ask in Discord
                    </ULink>
                </div>
            </HomeReveal>
        </UContainer>
    </section>
</template>
