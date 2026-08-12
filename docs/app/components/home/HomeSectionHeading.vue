<script setup lang="ts">
withDefaults(defineProps<{ eyebrow: string, centered?: boolean }>(), { centered: false })
</script>

<template>
    <div :class="centered ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
        <p class="text-sm text-neutral-500">
            {{ eyebrow }}
        </p>

        <!-- font-sans overrides the global mono heading rule: mono is BenchKit's
             voice for numbers, not for display type or labels. -->
        <h2 class="mt-3 text-balance font-sans text-3xl font-semibold leading-[1.12] tracking-[-0.025em] text-white sm:text-4xl">
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
