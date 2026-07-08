<template>
    <!-- Running: a full-height CI console that fills the app shell -->
    <div v-if="state === 'running'" class="flex-1 min-h-0 w-full flex flex-col px-8 pt-6 pb-8">
        <div class="flex items-center justify-between mb-5 shrink-0">
            <h2 class="font-mono text-3xl text-white flex items-center">Turning up the heat... <img src="/images/icons/loading.svg" alt="Loading" class="h-8 w-8 ml-2"></h2>
            <button @click="cancelQueue()" class="px-[14px] py-2.5 rounded-lg border border-[#373A41] bg-[#0C0E12] hover:bg-[rgba(255,255,255,0.12)] transition-colors duration-200 text-sm text-[#CECFD2] font-mono cursor-pointer">Cancel run</button>
        </div>

        <div class="flex-1 min-h-0 flex gap-6">
            <aside class="w-[280px] shrink-0 overflow-y-auto">
                <BenchmarkJobList heading="👨‍🔬 Tests to run" />
            </aside>

            <div class="flex-1 min-h-0 flex flex-col">
                <div v-if="activeBenchmark === 'yabs' && form.geekbench" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2] shrink-0 mb-4">
                    <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                    Geekbench takes a while to run. Be patient (could be 10-15+ minutes).
                </div>
                <div v-if="activeBenchmark === 'php' && form.php_mode === 'full'" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2] shrink-0 mb-4">
                    <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                    The full PHP suite runs 80+ benchmarks and can take 30+ minutes. Quick mode covers the headline CRUD metrics in about a minute.
                </div>

                <BenchmarkConsole height-class="flex-1 min-h-0" />
            </div>
        </div>
    </div>

    <!-- Completed: the collapsible detailed logs shown under the share card -->
    <div v-else class="mx-auto max-w-screen-lg w-full grid grid-cols-3 gap-x-4 mt-12">
        <div class="col-span-1 px-4">
            <BenchmarkJobList />
        </div>
        <div class="col-span-2">
            <BenchmarkConsole height-class="h-[calc(100vh-200px)]" />
        </div>
    </div>
</template>

<script setup>
import BenchmarkJobList from '@/Components/BenchmarkJobList.vue';
import BenchmarkConsole from '@/Components/BenchmarkConsole.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';
import { useEventBus } from '@vueuse/core';

const {
    form
} = useSettings();

const {
    state,
    activeBenchmark,
    appendOutput,
    setStatus,
    nextBenchmark,
    cancelQueue
} = useBenchmarkQueue();

const streamEventBus = useEventBus('stream-event-bus');

const listener = (message, data) => {
    if( message === 'benchmark:output' ) {
        let benchmark = JSON.parse(data);

        if( benchmark.type === 'out' || benchmark.type === 'err' ) {
            appendOutput(activeBenchmark.value, benchmark.output);
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
</script>
