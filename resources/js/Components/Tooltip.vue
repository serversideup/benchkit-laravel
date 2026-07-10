<template>
    <span class="relative inline-flex group">
        <slot />
        <span role="tooltip"
            class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-full mt-2 whitespace-nowrap rounded-lg border border-[#373A41] bg-[#0C0E12] px-3 py-1.5 text-xs font-medium text-[#CECFD2] shadow-xl shadow-black/40 opacity-0 translate-y-0.5 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-200 delay-300 z-20">
            {{ label }}
        </span>
    </span>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
});
</script>
