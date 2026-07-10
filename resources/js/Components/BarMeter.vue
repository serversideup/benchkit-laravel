<template>
    <span class="rounded-sm bg-[#22262F] overflow-hidden">
        <span v-if="percent != null" class="block h-full rounded-r-[4px]" :style="`width: ${percent}%; background-color: ${color};`"></span>
    </span>
</template>

<script setup>
defineProps({
    // Callers compute the percentage against their own scale; null renders
    // an empty track
    percent: {
        type: Number,
        default: null,
    },
    color: {
        type: String,
        default: '#94979C',
    },
});
</script>
