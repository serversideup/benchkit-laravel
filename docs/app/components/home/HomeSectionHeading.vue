<script setup lang="ts">
withDefaults(defineProps<{ eyebrow: string, centered?: boolean }>(), { centered: false })
</script>

<template>
    <div :class="centered ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
        <!-- The tracked mono eyebrow is a signature, so it marks structure only:
             section openings and panel groups. Data labels inside tables and
             cards stay sentence-case sans. -->
        <div
            class="flex items-center gap-3"
            :class="centered ? 'justify-center' : ''"
        >
            <span
                aria-hidden="true"
                class="h-px w-6 bg-flame-500/70"
            />
            <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-neutral-500">
                {{ eyebrow }}
            </span>
        </div>

        <h2 class="mt-5 text-balance text-3xl font-semibold leading-[1.12] tracking-[-0.025em] text-white sm:text-4xl">
            <slot name="title" />
        </h2>

        <p
            v-if="$slots.description"
            class="mt-4 text-pretty text-lg leading-relaxed text-neutral-400"
        >
            <slot name="description" />
        </p>
    </div>
</template>
