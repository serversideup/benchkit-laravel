<template>
    <div class="w-full flex flex-col items-center justify-center py-16">
        <div class="w-full flex flex-col items-center justify-center">
            <button @click="startBenchkit()" :disabled="runSummary.length === 0" class="font-mono px-[18px] py-3 inline-flex items-center border-2 border-[rgba(255,255,255,0.12)] rounded-lg text-white bg-[#E62E05] transition-all duration-300"
                :class="runSummary.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#E62E05]/80 cursor-pointer'">
                <img src="/images/ui/lightning.svg" alt="Lightning" class="h-5 mr-2">
                Start Benchmark
            </button>

            <div class="inline-flex rounded-lg border border-[#373A41] bg-[#0C0E12] p-1 font-mono text-sm mt-5">
                <button @click="applyPreset('quick')" class="px-4 py-1.5 rounded-md cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'quick' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Quick <span class="text-[#61656C]">&middot; ~2 min</span>
                </button>
                <button @click="applyPreset('full')" class="px-4 py-1.5 rounded-md cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'full' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Full <span class="text-[#61656C]">&middot; ~30+ min</span>
                </button>
                <button @click="openDrawer()" class="px-4 py-1.5 rounded-md cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'custom' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Custom <span v-if="activePreset === 'custom'" class="text-[#61656C]">&middot; {{ estimateLabel }}</span>
                </button>
            </div>

            <p v-if="runSummary.length" class="mt-3 text-xs text-[#94979C] font-mono">
                Running tests for: {{ runSummary.join(' · ') }}
            </p>
            <p v-else class="mt-3 text-xs text-[#94979C] font-mono">
                No tests selected &mdash; choose a preset or customize.
            </p>
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
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const startBenchkit = () => {
    startQueue();
}

const {
    startQueue,
} = useBenchmarkQueue();

const {
    applyPreset,
    activePreset,
    estimateLabel,
    runSummary,
} = useSettings();

const { open: openDrawer } = useSettingsDrawer();
</script>