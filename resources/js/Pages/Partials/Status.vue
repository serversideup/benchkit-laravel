<template>
    <div class="flex w-4 h-4 items-center justify-center">
        <img src="/images/icons/statuses/pending.svg" alt="Pending" class="h-4 w-4" v-if="status === 'pending'">
        <img src="/images/icons/statuses/running.svg" alt="Running" class="h-4 w-4 animate-spin" v-if="status === 'running'">
        <img src="/images/icons/statuses/completed.svg" alt="Completed" class="h-4 w-4" v-if="status === 'completed'">
        <img src="/images/icons/statuses/failed.svg" alt="Failed" class="h-4 w-4" v-if="status === 'error'">
        <img src="/images/icons/statuses/skipped.svg" alt="Skipped" class="h-2 w-2" v-if="status === 'skipped'">
    </div>
</template>

<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true
    }
});
</script>