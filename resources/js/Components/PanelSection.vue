<template>
    <section class="rounded-xl border border-[#22262F] bg-[#0C0E12] p-6 sm:p-8">
        <div class="flex items-start justify-between gap-x-6 gap-y-4 flex-wrap">
            <div class="min-w-0">
                <!-- The tracked mono eyebrow marks a panel opening; the title
                     itself is sans, because mono is BenchKit's voice for
                     numbers and labels rather than for display type. -->
                <div v-if="eyebrow" class="flex items-center gap-3">
                    <span aria-hidden="true" class="h-px w-6 bg-[#E62E05]/70"></span>
                    <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-[#61656C]">{{ eyebrow }}</span>
                </div>

                <h2 class="text-xl sm:text-2xl font-semibold leading-tight tracking-[-0.02em] text-[#F7F7F7]" :class="eyebrow ? 'mt-3' : ''">{{ title }}</h2>
            </div>

            <span v-if="$slots.aside" class="flex flex-wrap items-center gap-2">
                <slot name="aside" />
            </span>
        </div>

        <slot />
    </section>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    // The tool that produced these numbers. Naming it is how the page says
    // "this is a measurement, and here is what took it".
    eyebrow: {
        type: String,
        default: null,
    },
});
</script>
