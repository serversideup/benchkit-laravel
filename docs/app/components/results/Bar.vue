<template>
    <span class="block rounded-sm bg-[#22262F] overflow-hidden">
        <span
            v-if="percent != null"
            class="block h-full rounded-r-[4px]"
            :style="`width: ${percent}%; background-color: ${color};`"
        />
    </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ percent?: number | null, color?: string }>(), {
    percent: null,
    color: '#94979C'
})
</script>
