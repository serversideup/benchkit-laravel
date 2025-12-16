<template>
    <div class="w-full flex flex-col items-center justify-center py-16">
        <img src="/images/logos/benchkit-wide.svg" alt="Benchkit" class="h-16">

        <div class="w-full flex flex-col items-center justify-center mt-12">
            <button @click="startBenchkit()" class="font-mono px-[18px] py-3 inline-flex items-center border-2 border-[rgba(255,255,255,0.12)] rounded-lg text-white bg-[#E62E05] hover:bg-[#E62E05]/80 transition-all duration-300 cursor-pointer">
                <img src="/images/ui/lightning.svg" alt="Lightning" class="h-5 mr-2">
                Start Benchmark
            </button>
        </div>

        <div class="mx-auto w-[700px] flex flex-col items-center justify-center mt-12">
            <div class="flex items-center justify-between w-full rounded-t-lg bg-[rgba(255,255,255,0.50)] py-2 px-3">
                <div>
                    <img src="/images/ui/window-controls.svg" alt="Window Controls"/>
                </div>
            </div>
            <div class="w-full py-2 px-4 bg-[#13161B] rounded-b-lg flex flex-col">
                <img src="/images/ui/your-environment.svg" alt="Your Environment" class="py-4 w-80"/>

                <Server />
                <Php />
                <Laravel />
            </div>
        </div>
    </div>
</template>

<script setup>
import Server from '@/Pages/Partials/Server.vue';
import Php from '@/Pages/Partials/Php.vue';
import Laravel from '@/Pages/Partials/Laravel.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';

const {
    form
} = useSettings();

const startBenchkit = () => {
    const firstBenchmark = findFirstBenchmark();
    startBenchmark(firstBenchmark);
}

const findFirstBenchmark = () => {
    if( form.hardware ) {
        return 'yabs';
    }else if( form.network ) {
        return 'cfspeedtest';
    }else if( form.php_database ) {
        return 'php';
    }
}

const {
    startBenchmark,
} = useBenchmarkQueue();
</script>