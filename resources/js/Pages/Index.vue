<template>
    <div class="flex-1 w-full flex flex-col">
        <Home v-if="state == 'idle'" key="idle" class="state-in" />

        <!-- The run's process died without finishing (a container restart
             mid-run, most likely). Nothing is still going, so the only
             thing to do is acknowledge it and start again. -->
        <div v-else-if="state == 'interrupted'" key="interrupted" class="state-in flex-1 flex flex-col items-center justify-center gap-4">
            <p class="text-lg font-semibold text-[#F7F7F7]">Your last run was interrupted</p>
            <p class="text-sm text-[#94979C] max-w-md text-center">
                {{ run?.error ?? 'The benchmark stopped before it finished.' }}
            </p>
            <button @click="dismiss()" type="button" class="px-4 py-2.5 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] hover:bg-[#13161B] hover:border-[#61656C] transition-colors duration-200 cursor-pointer">
                Back to home
            </button>
        </div>

        <div v-else-if="state == 'completed' && saveState === 'empty'" key="empty" class="state-in flex-1 flex flex-col items-center justify-center gap-4">
            <p class="text-lg font-semibold text-[#F7F7F7]">No results to save</p>
            <p class="text-sm text-[#94979C] max-w-md text-center">
                Every benchmark stage failed or was skipped, so there is nothing to record.
            </p>
            <button @click="dismiss()" type="button" class="px-4 py-2.5 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] hover:bg-[#13161B] hover:border-[#61656C] transition-colors duration-200 cursor-pointer">
                Back to home
            </button>
        </div>

        <div v-else-if="state == 'completed' && saveState === 'failed'" key="failed" class="state-in flex-1 flex flex-col items-center justify-center gap-4">
            <p class="text-lg font-semibold text-[#F7F7F7]">Couldn't save your results</p>
            <p class="text-sm text-[#94979C] max-w-md text-center">
                The benchmark finished, but saving the run snapshot failed. Your results are
                still in memory — retry before leaving this page.
            </p>
            <button @click="retry()" type="button" class="px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                Retry saving
            </button>
        </div>

        <!-- Covers running AND completed-while-saving: the finished console
             stays on screen (with a quiet saving indicator in its header)
             until the run page takes over — no interstitial screen -->
        <Running v-else key="running" class="state-in" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/App.vue';
import Home from '@/Pages/Partials/Home.vue';
import Running from '@/Pages/Partials/Running.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useDocumentTitle } from '@/Composables/useDocumentTitle';
import { useRunSnapshot } from '@/Composables/useRunSnapshot';

defineOptions({
    layout: AppLayout,
});

const {
    state,
    run,
    hydrate,
    unfollow,
    dismiss,
} = useBenchmarkQueue();

useDocumentTitle();

const { saveState, lastRunId, retry } = useRunSnapshot();

// The run in progress according to the server, which is what lets a tab
// opened (or reloaded) mid-run join the live console instead of offering
// a Start button that would be refused.
const activeRun = computed(() => usePage().props.activeRun ?? null);

onMounted(() => hydrate(activeRun.value));
onUnmounted(unfollow);

// Once the snapshot lands, the run page (fed by the durable snapshot) takes
// over. The run is only forgotten AFTER the visit finishes — forgetting
// first would flash the Home page mid-navigation. Immediate: the run may
// have finished and saved while this watcher was unmounted.
watch(saveState, (value) => {
    if( value === 'saved' && lastRunId.value && state.value === 'completed' ) {
        router.visit(`/runs/${lastRunId.value}`, {
            onFinish: dismiss,
        });
    }
}, { immediate: true });
</script>

<style scoped>
/* A CSS animation (not a Vue <Transition>) so a state swap can never strand
   the page: <Transition mode="out-in"> removes the old view and only inserts
   the new one after rAF-driven leave choreography completes — if that stalls
   (e.g. after an HMR patch), the page is left permanently blank. An animation
   plays natively on the freshly mounted element; worst case it simply
   doesn't animate. */
@keyframes state-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* fill-mode backwards (not both): a forward-filled transform would turn the
   view into the containing block for its fixed-position modals */
.state-in {
    animation: state-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}
</style>
