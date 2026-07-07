<template>
    <div>
        <Home v-if="state == 'idle'" />
        <Running
            v-if="state == 'running'" />
        <Completed
            v-if="state == 'completed'" />
    </div>
</template>

<script setup>
import AppLayout from '@/Layouts/App.vue';
import Home from '@/Pages/Partials/Home.vue';
import Running from '@/Pages/Partials/Running.vue';
import Completed from '@/Pages/Partials/Completed.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useDocumentTitle } from '@/Composables/useDocumentTitle';

defineOptions({
    layout: AppLayout,
});

const {
    state
} = useBenchmarkQueue();

useDocumentTitle();
</script>