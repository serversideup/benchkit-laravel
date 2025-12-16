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
                <button @click="viewBenchmark('yabs')" class="cursor-pointer flex items-center justify-between py-2 px-3 font-mono text-[#ECECED] rounded-md mb-1"
                    :class="{ 
                        'bg-[#22262F]' : viewingBenchmark === 'yabs',
                        'bg-[#0C0E12]' : viewingBenchmark !== 'yabs',
                    }">
                    Hardware

                    <Status :status="results['yabs'].status" />
                </button>
                <button @click="viewBenchmark('cfspeedtest')" class="cursor-pointer flex items-center justify-between py-2 px-3 font-mono text-[#ECECED] rounded-md mb-1"
                    :class="{ 
                        'bg-[#22262F]' : viewingBenchmark === 'cfspeedtest',
                        'bg-[#0C0E12]' : viewingBenchmark !== 'cfspeedtest',
                    }">
                    Network

                    <Status :status="results['cfspeedtest'].status" />
                </button>
                <button @click="viewBenchmark('php')" class="cursor-pointer flex items-center justify-between py-2 px-3 font-mono text-[#ECECED] rounded-md mb-1"
                    :class="{ 
                        'bg-[#22262F]' : viewingBenchmark === 'php',
                        'bg-[#0C0E12]' : viewingBenchmark !== 'php',
                    }">
                    PHP

                    <Status :status="results['php'].status" />
                </button>
            </div>
        </div>
        <div class="col-span-2">
            <h2 v-show="state === 'running'" class="font-mono text-3xl mb-2.5 text-white flex items-center justify-between">Turning up the heat... <img src="/images/icons/loading.svg" alt="Loading" class="h-8 w-8 ml-2"></h2>
            <div v-if="activeBenchmark === 'yabs' && form.geekbench" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2]">
                <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                Geekbench takes a while to run. Be patient (could be 10-15+ minutes).
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
                    :key="output" class="text-xs font-mono text-white"
                    v-text="output"/>
            </div>
        </div>
    </div>
</template>

<script setup>
import Status from '@/Pages/Partials/Status.vue';
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
    nextBenchmark
} = useBenchmarkQueue();

const streamEventBus = useEventBus('stream-event-bus');

const listener = (message, data) => {
    if( message === 'benchmark:output' ) {
        let benchmark = JSON.parse(data);

        if( benchmark.type === 'out' ) {
            appendOutput(activeBenchmark.value, benchmark.output.trim());
        }

        if( benchmark.type == 'heartbeat' ){
            
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