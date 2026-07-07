<template>
    <div class="mx-auto max-w-screen-lg w-full grid grid-cols-3 gap-x-4 mt-12">
        <div class="col-span-1 flex flex-col px-4"
            :class="{
                'pt-8' : state === 'running',
                'pt-0' : state === 'completed',
            }">
            <h2 class="font-mono font-bold text-sm text-[#CECFD2]" v-show="state === 'running'">👨‍🔬 Tests to run</h2>

            <div class="flex flex-col"
                :class="{
                    'mt-4' : state === 'running',
                    'mt-0' : state === 'completed',
                }">
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
        <div class="col-span-2">
            <div v-show="state === 'running'" class="flex items-center justify-between mb-2.5">
                <h2 class="font-mono text-3xl text-white flex items-center">Turning up the heat... <img src="/images/icons/loading.svg" alt="Loading" class="h-8 w-8 ml-2"></h2>
                <button @click="cancelQueue()" class="px-[14px] py-2.5 rounded-lg border border-[#373A41] bg-[#0C0E12] hover:bg-[rgba(255,255,255,0.12)] transition-colors duration-200 text-sm text-[#CECFD2] font-mono cursor-pointer">Cancel run</button>
            </div>
            <div v-if="activeBenchmark === 'yabs' && form.geekbench" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2]">
                <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                Geekbench takes a while to run. Be patient (could be 10-15+ minutes).
            </div>
            <div v-if="activeBenchmark === 'php' && form.php_mode === 'full'" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2]">
                <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                The full PHP suite runs 80+ benchmarks and can take 30+ minutes. Quick mode covers the headline CRUD metrics in about a minute.
            </div>
            <div v-if="results[viewingBenchmark].status === 'pending'" class="w-full p-8 rounded-lg border border-[#373A41] bg-[#13161B] overflow-y-auto mt-2.5">
                <pre 
                    class="text-xs font-mono text-white"
                    v-text="'Waiting for other jobs to complete...'"/>
            </div>
            <div v-else-if="results[viewingBenchmark].status === 'skipped'" class="w-full p-8 rounded-lg border border-[#373A41] bg-[#13161B] overflow-y-auto mt-2.5">
                <pre 
                    class="text-xs font-mono text-white"
                    v-text="'This benchmark has been skipped. Please check your settings and try again.'"/>
            </div>
            <div v-else class="w-full p-8 rounded-lg border border-[#373A41] bg-[#13161B] h-[calc(100vh-200px)] overflow-y-auto mt-2.5">
                <pre
                    v-for="output in results[viewingBenchmark].output"
                    :key="output" class="text-xs font-mono text-white whitespace-pre-wrap break-words"
                    v-text="output"/>
            </div>
        </div>
    </div>
</template>

<script setup>
import Status from '@/Pages/Partials/Status.vue';
import { ref, onUnmounted } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';
import { useEventBus } from '@vueuse/core';

const {
    form
} = useSettings();

const {
    state,
    results,
    activeBenchmark,
    userViewingBenchmark,
    viewingBenchmark,
    appendOutput,
    setStatus,
    nextBenchmark,
    cancelQueue
} = useBenchmarkQueue();

const benchmarks = [
    { key: 'yabs', label: 'Hardware' },
    { key: 'cfspeedtest', label: 'Network' },
    { key: 'http', label: 'Web Server' },
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

const streamEventBus = useEventBus('stream-event-bus');

const listener = (message, data) => {
    if( message === 'benchmark:output' ) {
        let benchmark = JSON.parse(data);

        if( benchmark.type === 'out' || benchmark.type === 'err' ) {
            appendOutput(activeBenchmark.value, benchmark.output.trim());
        }

        // Long-running subjects can go a minute or more between output lines —
        // surface the server heartbeat so the run never looks hung
        if( benchmark.type == 'heartbeat' ){
            appendOutput(activeBenchmark.value, `... still running (${benchmark.timestamp} UTC)`);
        }

        if( benchmark.status === 'completed' ) {
            setStatus(activeBenchmark.value, 'completed');
            nextBenchmark();
        }

        if( benchmark.status === 'error' ) {
            setStatus(activeBenchmark.value, 'error');
            nextBenchmark();
        }
    }
}
streamEventBus.on(listener);

const viewBenchmark = (benchmark) => {
    userViewingBenchmark.value = true;
    viewingBenchmark.value = benchmark;
}
</script>