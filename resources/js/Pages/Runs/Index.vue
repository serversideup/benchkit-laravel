<template>
    <div class="w-full">
        <div class="w-full max-w-screen-lg mx-auto flex flex-col py-12 px-4 gap-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold text-[#F7F7F7]">Run history</h1>
                    <p class="mt-1 text-sm text-[#94979C]">Select two runs to compare what changed.</p>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <Link v-if="selected.length === 2" :href="`/compare/${selected[0]}/${selected[1]}`"
                        class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                        Compare selected
                    </Link>
                    <Link href="/" class="px-4 py-2.5 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] hover:bg-[#13161B] hover:border-[#61656C] transition-colors duration-200 cursor-pointer">
                        New benchmark
                    </Link>
                </div>
            </div>

            <RunHistoryList v-if="runs.length" :runs="runs" selectable deletable v-model:selected="selected" />

            <div v-else class="rounded-xl border border-[#22262F] bg-[#0C0E12] p-12 flex flex-col items-center gap-4 text-center">
                <p class="text-lg font-semibold text-[#F7F7F7]">No runs yet</p>
                <p class="text-sm text-[#94979C] max-w-md">
                    Complete a benchmark and it will be saved here automatically —
                    run history survives container rebuilds when <span class="font-mono text-[#CECFD2]">storage/app/runs</span> is mounted as a volume.
                </p>
                <Link href="/" class="mt-2 px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                    Start a benchmark
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/App.vue';
import RunHistoryList from '@/Components/Runs/RunHistoryList.vue';
import { useDocumentTitle } from '@/Composables/useDocumentTitle';

defineOptions({
    layout: AppLayout,
});

defineProps({
    runs: {
        type: Array,
        required: true,
    },
});

useDocumentTitle();

const selected = ref([]);
</script>
