<template>
    <div class="flex justify-between gap-3">
        <dt class="text-dimmed shrink-0">{{ label }}</dt>
        <dd class="text-muted text-right truncate">{{ value }}</dd>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    label: string
    value: string | number
}>()
</script>
