<template>
    <div class="w-full">
        <div class="w-full max-w-4xl mx-auto flex flex-col py-12 px-4">
            <Link href="/runs" class="rise-in text-sm text-[#94979C] hover:text-[#CECFD2]">&larr; Run history</Link>

            <div class="rise-in mt-5 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between" style="animation-delay: 70ms;">
                <!-- Identity: the run's name and when/where it ran, kept as one
                     group so nothing wedges between the title and its metadata -->
                <div class="flex flex-col min-w-0 gap-2">
                    <RunMetaEditor :run-id="run.id" :meta="meta" @updated="meta = $event" />

                    <!-- Sans, not mono: this is a sentence about the run, and a
                         six-item mono chain under a title reads as output
                         rather than as context. -->
                    <p class="text-sm text-[#94979C] flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <span class="whitespace-nowrap">{{ display.timestamp }}</span>
                        <span class="text-[#61656C]">&middot;</span>
                        <button v-if="hostSummary" @click="detailsOpen = !detailsOpen" type="button" class="hover:text-[#CECFD2] hover:underline underline-offset-4 decoration-[#373A41] cursor-pointer text-left" title="Edit hosting details">{{ hostSummary }}</button>
                        <button v-else @click="detailsOpen = true" type="button" class="text-[#61656C] hover:text-[#CECFD2] cursor-pointer transition-colors duration-200">+ Add hosting details</button>
                    </p>
                </div>

                <!-- Actions: two quiet utilities as one segmented group, then a
                     single filled primary — a toolbar, not scattered buttons.
                     Full-width split on mobile, compact cluster on desktop. -->
                <div class="flex flex-wrap items-center gap-3 shrink-0 w-full sm:w-auto sm:justify-end">
                    <div class="flex items-center rounded-lg border border-[#22262F] bg-[#0C0E12] p-0.5">
                        <IconButton label="Download results (zip)" @click="download()">
                            <IconDownloadCloud />
                        </IconButton>
                        <IconButton label="Run the benchmark again" @click="runAgain()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="none">
                                <path d="M1.6665 8.33333C1.6665 8.33333 3.33732 6.05685 4.6947 4.69854C6.05208 3.34022 7.92783 2.5 9.99984 2.5C14.142 2.5 17.4998 5.85786 17.4998 10C17.4998 14.1421 14.142 17.5 9.99984 17.5C6.58059 17.5 3.69576 15.2119 2.79298 12.0833M1.6665 8.33333V3.33333M1.6665 8.33333H6.6665" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </IconButton>
                    </div>
                    <!-- Submitting to the gallery is now the primary action; sharing on X is the quieter secondary -->
                    <button @click="shareOpen = true" type="button" class="px-4 py-2.5 rounded-lg flex items-center text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#22262F] hover:bg-[#13161B] hover:text-white transition-colors duration-200 cursor-pointer">
                        Share on
                        <IconXLogo class="ml-2" />
                    </button>
                    <button @click="submitOpen = true" type="button" class="grow sm:grow-0 justify-center px-4 py-2.5 rounded-lg flex items-center text-sm font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                        Submit result
                        <IconArrowUpRight class="ml-2" :size="18" />
                    </button>
                </div>
            </div>

            <HostDetailsPanel v-if="detailsOpen" class="mt-5" :run-id="run.id" :meta="meta"
                @updated="meta = $event" @close="detailsOpen = false" />

            <RunCaveats class="rise-in mt-8" style="animation-delay: 150ms;"
                :environment="run.environment" :http="display.http" />

            <!-- Separate cards rather than divisions of one long box: it gives each
                 measurement its own edge and lets the page breathe. -->
            <div class="rise-in mt-8 flex flex-col gap-4" style="animation-delay: 180ms;">
                <HttpPanel v-if="display.http" :http="display.http" />
                <!-- The raw benchmarks.php, not display.php: this panel calls
                     crudHeadlines() and suiteTotalMs(), which read `headline`
                     and the per-subject spread. runDisplay has already
                     flattened those away, so passing it silently cost the
                     comparability check, the bars, and the variance line. -->
                <PhpCrudPanel v-if="run.benchmarks.php" :php="run.benchmarks.php" :mode="run.settings?.php_mode" />
                <NetworkPanel v-if="display.network" :network="display.network" :provider="meta.provider" />
                <HardwarePanel v-if="display.hardware && (display.geekbench || display.hardware.fio?.length)" :hardware="display.hardware" :geekbench="display.geekbench" />
                <EnvironmentPanel :environment="run.environment" :hardware="display.hardware" />
                <RunLogs :logs="run.logs" />
            </div>
        </div>

        <ShareModal :open="shareOpen" :run="runWithMeta" @close="shareOpen = false" />
        <SubmitModal :open="submitOpen" :run="runWithMeta" @close="submitOpen = false" @share="submitOpen = false; shareOpen = true" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/App.vue';
import IconButton from '@/Components/IconButton.vue';
import IconDownloadCloud from '@/Components/Icons/IconDownloadCloud.vue';
import IconXLogo from '@/Components/Icons/IconXLogo.vue';
import IconArrowUpRight from '@/Components/Icons/IconArrowUpRight.vue';
import RunMetaEditor from '@/Components/Runs/RunMetaEditor.vue';
import HostDetailsPanel from '@/Components/Runs/HostDetailsPanel.vue';
import RunCaveats from '@/Components/Runs/RunCaveats.vue';
import HttpPanel from '@/Components/Runs/HttpPanel.vue';
import PhpCrudPanel from '@/Components/Runs/PhpCrudPanel.vue';
import NetworkPanel from '@/Components/Runs/NetworkPanel.vue';
import HardwarePanel from '@/Components/Runs/HardwarePanel.vue';
import EnvironmentPanel from '@/Components/Runs/EnvironmentPanel.vue';
import RunLogs from '@/Components/Runs/RunLogs.vue';
import ShareModal from '@/Components/Share/ShareModal.vue';
import SubmitModal from '@/Components/Submit/SubmitModal.vue';
import { hostDetailsLine, runDisplay } from '@/Composables/useRunSummary';
import { downloadRunResults } from '@/Composables/useRunActions';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useDocumentTitle } from '@/Composables/useDocumentTitle';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
});

useDocumentTitle();

const meta = ref({ ...props.run.meta });
const display = computed(() => runDisplay(props.run));

const hostSummary = computed(() => hostDetailsLine(meta.value));
const runWithMeta = computed(() => ({ ...props.run, meta: meta.value }));
const shareOpen = ref(false);
const submitOpen = ref(false);
const detailsOpen = ref(false);

const download = () => downloadRunResults(runWithMeta.value);

const { startQueue } = useBenchmarkQueue();

// Start first, then navigate: the home page renders whatever run the
// server reports, so visiting before the run exists would land on the
// start screen until the next poll picked it up.
const runAgain = async () => {
    await startQueue();
    router.visit('/');
};
</script>

<style scoped>
@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* fill-mode backwards (not both): keeps elements hidden through their
   stagger delay without forward-filling a transform, which would make each
   block the containing block for any fixed-position descendant */
.rise-in {
    animation: rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}
</style>
