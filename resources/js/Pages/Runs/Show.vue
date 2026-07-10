<template>
    <div class="w-full">
        <div class="w-full max-w-4xl mx-auto flex flex-col py-12 px-4">
            <Link href="/runs" class="rise-in text-sm text-[#94979C] hover:text-[#CECFD2]">&larr; Run history</Link>

            <div class="rise-in mt-6 flex flex-col sm:flex-row sm:items-start justify-between gap-5" style="animation-delay: 70ms;">
                <RunMetaEditor class="flex-1 min-w-0" :run-id="run.id" :meta="meta" @updated="meta = $event" />

                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex items-center gap-1">
                        <IconButton label="Download results (zip)" @click="download()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none">
                                <path d="M2.50016 11.8685C1.49517 11.1958 0.833496 10.0502 0.833496 8.75C0.833496 6.79702 2.32642 5.19274 4.23328 5.01614C4.62334 2.64344 6.6837 0.833332 9.16683 0.833332C11.65 0.833332 13.7103 2.64344 14.1004 5.01614C16.0072 5.19274 17.5002 6.79702 17.5002 8.75C17.5002 10.0502 16.8385 11.1958 15.8335 11.8685M5.8335 12.5L9.16683 15.8333M9.16683 15.8333L12.5002 12.5M9.16683 15.8333V8.33333" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </IconButton>
                        <IconButton label="Run the benchmark again" @click="runAgain()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="none">
                                <path d="M1.6665 8.33333C1.6665 8.33333 3.33732 6.05685 4.6947 4.69854C6.05208 3.34022 7.92783 2.5 9.99984 2.5C14.142 2.5 17.4998 5.85786 17.4998 10C17.4998 14.1421 14.142 17.5 9.99984 17.5C6.58059 17.5 3.69576 15.2119 2.79298 12.0833M1.6665 8.33333V3.33333M1.6665 8.33333H6.6665" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </IconButton>
                    </div>
                    <button @click="shareOpen = true" type="button" class="px-4 py-2.5 rounded-lg flex items-center text-sm font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                        Share on
                        <svg class="ml-2" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 18 18" fill="none">
                            <path d="M10.1909 7.41006L16.5656 0H15.055L9.51988 6.43405L5.09898 0H0L6.68527 9.72942L0 17.5H1.51068L7.35593 10.7054L12.0247 17.5H17.1237L10.1906 7.41006H10.1909ZM8.12184 9.81514L7.44449 8.84631L2.055 1.13722H4.37532L8.7247 7.3587L9.40206 8.32753L15.0557 16.4145H12.7354L8.12184 9.81551V9.81514Z" fill="white"/>
                        </svg>
                    </button>
                </div>
            </div>

            <p class="rise-in text-sm text-[#94979C] font-mono mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5" style="animation-delay: 120ms;">
                <span class="whitespace-nowrap">{{ display.timestamp }}</span>
                <span class="flex items-baseline gap-x-2 min-w-0">
                    <span>&middot;</span>
                    <button v-if="hostSummary" @click="detailsOpen = !detailsOpen" type="button" class="hover:text-[#CECFD2] hover:underline underline-offset-4 decoration-[#373A41] cursor-pointer text-left" title="Edit hosting details">{{ hostSummary }}</button>
                    <button v-else @click="detailsOpen = true" type="button" class="text-[#61656C] hover:text-[#CECFD2] cursor-pointer transition-colors duration-200">+ Add hosting details</button>
                </span>
            </p>

            <HostDetailsPanel v-if="detailsOpen" class="mt-5" :run-id="run.id" :meta="meta"
                @updated="meta = $event" @close="detailsOpen = false" />

            <div class="rise-in mt-8 rounded-xl border border-[#22262F] bg-[#0C0E12] px-6 sm:px-8 divide-y divide-[#22262F]" style="animation-delay: 180ms;">
                <HttpPanel v-if="display.http" :http="display.http" />
                <PhpCrudPanel v-if="display.php" :php="display.php" :mode="run.settings?.php_mode" />
                <NetworkPanel v-if="display.network" :network="display.network" :provider="meta.provider" />
                <HardwarePanel v-if="display.hardware && (display.geekbench || display.hardware.fio?.length)" :hardware="display.hardware" :geekbench="display.geekbench" />
                <EnvironmentPanel :environment="run.environment" :hardware="display.hardware" />
                <RunLogs :logs="run.logs" />
            </div>
        </div>

        <ShareModal :open="shareOpen" :run="runWithMeta" @close="shareOpen = false" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/App.vue';
import IconButton from '@/Components/IconButton.vue';
import RunMetaEditor from '@/Components/Runs/RunMetaEditor.vue';
import HostDetailsPanel from '@/Components/Runs/HostDetailsPanel.vue';
import HttpPanel from '@/Components/Runs/HttpPanel.vue';
import PhpCrudPanel from '@/Components/Runs/PhpCrudPanel.vue';
import NetworkPanel from '@/Components/Runs/NetworkPanel.vue';
import HardwarePanel from '@/Components/Runs/HardwarePanel.vue';
import EnvironmentPanel from '@/Components/Runs/EnvironmentPanel.vue';
import RunLogs from '@/Components/Runs/RunLogs.vue';
import ShareModal from '@/Components/Share/ShareModal.vue';
import { runDisplay } from '@/Composables/useRunSummary';
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

const hostSummary = computed(() => [
    meta.value.provider,
    meta.value.plan ?? meta.value.plan_notes,
    meta.value.datacenter,
    meta.value.cost,
].filter(Boolean).join(' · '));
const runWithMeta = computed(() => ({ ...props.run, meta: meta.value }));
const shareOpen = ref(false);
const detailsOpen = ref(false);

const download = () => downloadRunResults(runWithMeta.value);

const { startQueue } = useBenchmarkQueue();

const runAgain = () => {
    startQueue();
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
