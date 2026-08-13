<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.02] p-6 sm:p-8">
        <div class="flex items-start justify-between gap-x-6 gap-y-4 flex-wrap">
            <div class="min-w-0">
                <!-- The tracked mono eyebrow is the site's structural signature.
                     It marks a panel opening; the title itself is sans, because
                     mono is BenchKit's voice for numbers and labels rather than
                     for display type. -->
                <div
                    v-if="eyebrow"
                    class="flex items-center gap-3"
                >
                    <span
                        aria-hidden="true"
                        class="h-px w-6 bg-flame-500/70"
                    />
                    <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-500">
                        {{ eyebrow }}
                    </span>
                </div>

                <h2
                    class="font-sans text-xl font-semibold leading-tight tracking-[-0.02em] text-white sm:text-2xl"
                    :class="eyebrow ? 'mt-3' : ''"
                >
                    {{ title }}
                </h2>
            </div>

            <span
                v-if="$slots.aside"
                class="flex flex-wrap items-center gap-2"
            ><slot name="aside" /></span>
        </div>

        <p
            v-if="$slots.description"
            class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-neutral-400"
        >
            <slot name="description" />
        </p>

        <slot />
    </section>
</template>

<script setup lang="ts">
defineProps<{ title: string, eyebrow?: string }>()
</script>
