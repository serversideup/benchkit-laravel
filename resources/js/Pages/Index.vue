<template>
    <div>
        <Home v-if="state == 'idle'" />
        <Running v-if="state == 'running'" />
    </div>
    
</template>

<script setup>
import AppLayout from '@/Layouts/App.vue';
import Home from '@/Pages/Partials/Home.vue';
import Running from '@/Pages/Partials/Running.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

defineOptions({
    layout: AppLayout,
});

const {
    state
} = useBenchmarkQueue();
</script>