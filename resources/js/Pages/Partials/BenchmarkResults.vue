<template>
    <!-- A full-height CI console that fills the app shell. It also stays on
         screen after the last stage finishes, while the run snapshot saves
         and the run page loads — the header swaps the Cancel button for a
         quiet saving indicator instead of cutting to a separate screen. -->
    <div class="flex-1 min-h-0 w-full flex flex-col px-8 pt-6 pb-8">
        <div class="flex items-center justify-between mb-5 shrink-0">
            <h2 v-if="state === 'running'" class="font-mono text-3xl text-white flex items-center">Turning up the heat... <img src="/images/icons/loading.svg" alt="Loading" class="h-8 w-8 ml-2"></h2>
            <h2 v-else class="font-mono text-3xl text-white">🏁 All tests complete</h2>

            <span v-if="stopping" class="flex items-center gap-2.5 text-sm text-[#94979C]">
                <img src="/images/icons/statuses/running.svg" alt="" class="h-4 w-4 animate-spin">
                Stopping the run&hellip;
            </span>
            <button v-else-if="state === 'running'" @click="confirmingCancel = true" type="button" class="px-4 py-2.5 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] hover:bg-[#13161B] hover:border-[#61656C] transition-colors duration-200 cursor-pointer">Cancel run</button>
            <span v-else class="flex items-center gap-2.5 text-sm text-[#94979C]">
                <img src="/images/icons/statuses/running.svg" alt="" class="h-4 w-4 animate-spin">
                Saving your results&hellip;
            </span>
        </div>

        <div class="flex-1 min-h-0 flex gap-6">
            <aside class="w-[280px] shrink-0 overflow-y-auto">
                <BenchmarkJobList heading="👨‍🔬 Tests to run" />
            </aside>

            <div class="flex-1 min-h-0 flex flex-col">
                <div v-if="state === 'running' && activeBenchmark === 'yabs' && settings.geekbench" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2] shrink-0 mb-4">
                    <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                    Geekbench takes a while to run. Be patient (could be 10-15+ minutes).
                </div>
                <div v-if="state === 'running' && activeBenchmark === 'php' && settings.php_mode === 'full'" class="flex items-center rounded-xl bg-[#13161B] p-4 border border-[#373A41] font-mono text-sm text-[#CECFD2] shrink-0 mb-4">
                    <img src="/images/icons/warning.svg" alt="Warning" class="h-5 w-5 mr-2">
                    The full PHP suite runs 80+ benchmarks and can take 30+ minutes. Quick mode covers the headline CRUD metrics in about a minute.
                </div>

                <BenchmarkConsole height-class="flex-1 min-h-0" />
            </div>
        </div>

        <!-- Cancelling is now a deliberate act rather than a side effect of
             closing the tab, and it throws away however long the run has
             been going — worth a confirmation. -->
        <ConfirmModal
            :open="confirmingCancel"
            title="Stop this run?"
            :message="`The benchmark has been running for ${elapsed}. Stopping it discards the results so far — nothing will be saved to your run history.`"
            confirm-label="Stop the run"
            @close="confirmingCancel = false"
            @confirm="stopRun()" />
    </div>
</template>

<script setup>
import { computed, ref, onUnmounted } from 'vue';
import BenchmarkJobList from '@/Components/BenchmarkJobList.vue';
import BenchmarkConsole from '@/Components/BenchmarkConsole.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';
import { formatClock } from '@/Composables/useRunSummary';

const {
    form
} = useSettings();

const {
    run,
    state,
    activeBenchmark,
    cancelQueue,
} = useBenchmarkQueue();

// The run's own settings, not this browser's: a tab that joined a run
// started elsewhere must describe the run that is actually happening.
const settings = computed(() => run.value?.settings ?? form);

// The run process stops at its next checkpoint rather than being killed
// outright, so there is a moment between asking and stopping.
const stopping = computed(() => state.value === 'running' && Boolean(run.value?.cancel_requested));

const confirmingCancel = ref(false);

const now = ref(Date.now());
const ticker = setInterval(() => now.value = Date.now(), 1000);
onUnmounted(() => clearInterval(ticker));

const elapsed = computed(() => {
    const startedAt = run.value?.started_at ? Date.parse(run.value.started_at) : null;

    return startedAt ? formatClock(now.value - startedAt) : 'a while';
});

const stopRun = () => {
    confirmingCancel.value = false;
    cancelQueue();
};
</script>
