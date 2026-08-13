<template>
    <span class="block overflow-hidden rounded-full bg-white/[0.06]">
        <span
            v-if="percent != null"
            class="block h-full rounded-full"
            :style="`width: ${percent}%; background-color: ${color};`"
        />
    </span>
</template>

<script setup lang="ts">
// Default is a neutral track fill rather than the brand red: on a page where
// every panel carries bars, reserving the accent for the one measurement being
// emphasised is what keeps it meaning anything.
withDefaults(defineProps<{ percent?: number | null, color?: string }>(), {
    percent: null,
    color: 'rgb(163 163 163 / 0.55)'
})
</script>
