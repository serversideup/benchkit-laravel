import { computed } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

const { run, retrySave } = useBenchmarkQueue();

/**
 * How the finished run's snapshot turned out.
 *
 * The run process saves its own snapshot, so a run that finishes while
 * nobody is watching is still recorded — this reads the outcome rather
 * than performing the save. 'empty' means every stage failed or was
 * skipped; 'failed' can be retried without re-running anything, because
 * the results and the console log are both already on disk.
 */
export const useRunSnapshot = () => {
    const saveState = computed(() => run.value?.save_state ?? 'idle');
    const lastRunId = computed(() => run.value?.snapshot_id ?? null);

    return {
        saveState,
        lastRunId,
        retry: retrySave,
    };
};
