<template>
    <!-- The CI console is the one view that fills the viewport and scrolls
         internally — bounded by the fixed 72px header height -->
    <div class="h-[calc(100dvh-72px)] min-h-0 w-full flex flex-col">
        <BenchmarkResults />
    </div>
</template>

<script setup>
import BenchmarkResults from '@/Pages/Partials/BenchmarkResults.vue';
</script>