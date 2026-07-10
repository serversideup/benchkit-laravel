<template>
    <div class="flex flex-col">
        <h2 v-if="heading" class="font-mono font-bold text-sm text-[#CECFD2]">{{ heading }}</h2>

        <div class="flex flex-col" :class="heading ? 'mt-4' : 'mt-0'">
            <button v-for="benchmark in STAGES" :key="benchmark.key" @click="viewBenchmark(benchmark.key)" class="cursor-pointer flex items-center justify-between py-2 px-3 font-mono text-[#ECECED] rounded-md mb-1"
                :class="{
                    'bg-[#22262F]' : viewingBenchmark === benchmark.key,
                    'bg-[#0C0E12]' : viewingBenchmark !== benchmark.key,
                }">
                {{ benchmark.label }}

                <span class="flex items-center">
                    <span v-if="elapsed(benchmark.key)" class="text-xs text-[#61656C] mr-2.5">{{ elapsed(benchmark.key) }}</span>
                    <Status :status="results[benchmark.key].status" />
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import Status from '@/Pages/Partials/Status.vue';
import { ref, onUnmounted } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { formatClock } from '@/Composables/useRunSummary';
import { STAGES } from '@/stages';

defineProps({
    heading: {
        type: String,
        default: null,
    },
});

const {
    results,
    viewingBenchmark,
    userViewingBenchmark,
} = useBenchmarkQueue();

const now = ref(Date.now());
const ticker = setInterval(() => now.value = Date.now(), 1000);
onUnmounted(() => clearInterval(ticker));

const elapsed = (benchmark) => {
    const { startedAt, endedAt } = results[benchmark];

    return startedAt ? formatClock((endedAt ?? now.value) - startedAt) : null;
}

const viewBenchmark = (benchmark) => {
    userViewingBenchmark.value = true;
    viewingBenchmark.value = benchmark;
}
</script>
