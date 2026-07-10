<template>
    <div class="w-full">
        <div class="w-full flex flex-col items-center py-16">
        <div class="w-full flex flex-col items-center justify-center">
            <button @click="startBenchkit()" :disabled="runSummary.length === 0" class="text-xl font-semibold px-12 py-5 inline-flex items-center border-2 border-[rgba(255,255,255,0.12)] rounded-xl text-white bg-[#E62E05] shadow-lg shadow-[#E62E05]/25 transition-all duration-300"
                :class="runSummary.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#F13D12] hover:shadow-[#E62E05]/40 cursor-pointer'">
                <img src="/images/ui/lightning.svg" alt="Lightning" class="h-6 mr-3">
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

            <button @click="showEndpointsModal = true" class="mt-6 text-xs font-mono text-[#94979C] hover:text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] cursor-pointer transition-colors duration-200">
                Prefer your own tools? You can run some tests externally too
            </button>
        </div>

        <div v-if="recentRuns.length" class="mx-auto w-[700px] flex flex-col mt-12">
            <div class="flex items-center justify-between">
                <h2 class="text-sm text-[#61656C] font-mono uppercase tracking-wider">Recent runs</h2>
                <Link href="/runs" class="text-xs text-[#94979C] font-mono hover:text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]">
                    View history &rarr;
                </Link>
            </div>
            <div class="mt-3">
                <RunHistoryList :runs="recentRuns" />
            </div>
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

        <ExternalEndpointsModal :open="showEndpointsModal" @close="showEndpointsModal = false" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Server from '@/Pages/Partials/Server.vue';
import Php from '@/Pages/Partials/Php.vue';
import Laravel from '@/Pages/Partials/Laravel.vue';
import ExternalEndpointsModal from '@/Components/ExternalEndpointsModal.vue';
import RunHistoryList from '@/Components/Runs/RunHistoryList.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const recentRuns = computed(() => usePage().props.recentRuns ?? []);

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

const showEndpointsModal = ref(false);
</script>