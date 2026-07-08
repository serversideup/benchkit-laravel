<template>
    <div class="flex flex-col">
        <h2 v-if="heading" class="font-mono font-bold text-sm text-[#CECFD2]">{{ heading }}</h2>

        <div class="flex flex-col" :class="heading ? 'mt-4' : 'mt-0'">
            <button v-for="benchmark in benchmarks" :key="benchmark.key" @click="viewBenchmark(benchmark.key)" class="cursor-pointer flex items-center justify-between py-2 px-3 font-mono text-[#ECECED] rounded-md mb-1"
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

const benchmarks = [
    { key: 'yabs', label: 'Hardware' },
    { key: 'cfspeedtest', label: 'Network' },
    { key: 'http', label: 'Web Server Load' },
    { key: 'php', label: 'PHP' },
];

const now = ref(Date.now());
const ticker = setInterval(() => now.value = Date.now(), 1000);
onUnmounted(() => clearInterval(ticker));

const elapsed = (benchmark) => {
    const { startedAt, endedAt } = results[benchmark];

    if( !startedAt ) {
        return null;
    }

    const seconds = Math.max(0, Math.floor(((endedAt ?? now.value) - startedAt) / 1000));
    const pad = (n) => String(n).padStart(2, '0');

    return `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`;
}

const viewBenchmark = (benchmark) => {
    userViewingBenchmark.value = true;
    viewingBenchmark.value = benchmark;
}
</script>
