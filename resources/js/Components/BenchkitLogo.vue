<template>
    <a v-if="state !== 'running'" href="/" title="Back to start">
        <img src="/images/logos/benchkit-wide.svg" alt="BenchKit" :class="sizeClass">
    </a>
    <!-- Inert while running: a full navigation would disconnect the stream
         and cancel the run — the Cancel button is the explicit way out -->
    <img v-else src="/images/logos/benchkit-wide.svg" alt="BenchKit" :class="sizeClass">
</template>

<script setup>
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

const { state } = useBenchmarkQueue();

defineProps({
    sizeClass: {
        type: String,
        default: 'h-16',
    },
});
</script>
