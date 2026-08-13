<template>
    <span class="rounded-full bg-[rgba(255,255,255,0.06)] overflow-hidden">
        <span v-if="percent != null" class="block h-full rounded-full" :style="`width: ${percent}%; background-color: ${color};`"></span>
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
        default: 'rgba(148, 151, 156, 0.55)',
    },
});
</script>
