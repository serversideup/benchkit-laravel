<template>
    <div class="w-full flex flex-col items-center justify-center py-16">
        <img src="/images/logos/benchkit-wide.svg" alt="Benchkit" class="h-16">

        <div class="mx-auto max-w-screen-lg w-full grid grid-cols-3 gap-x-4 mt-12">
            <div class="col-span-1">

            </div>
            <div class="col-span-2">
                <div class="w-full p-8 rounded-lg border border-[#373A41] bg-[#13161B]">
                    <pre 
                        v-for="output in results[activeBenchmark].output" 
                        :key="output" class="text-xs font-mono text-white"
                        v-text="output"/>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useStream } from '@/Composables/useStream';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useEventBus } from '@vueuse/core';

const {
    results,
    activeBenchmark,
    appendOutput,
    setStatus,
    nextBenchmark
} = useBenchmarkQueue();

const streamEventBus = useEventBus('stream-event-bus');

const listener = (message, data) => {
    if( message === 'benchmark:output' ) {
        let benchmark = JSON.parse(data);

        if( benchmark.type === 'out' ) {
            console.log(benchmark.output.trim());
            appendOutput(activeBenchmark.value, benchmark.output.trim());
        }

        if( benchmark.type == 'heartbeat' ){
            console.log(benchmark.output.trim());
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