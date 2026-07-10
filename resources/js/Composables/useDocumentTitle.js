import { watch, onUnmounted } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { formatClock } from '@/Composables/useRunSummary';
import { STAGE_LABELS } from '@/stages';

const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

// Drives the browser tab title from the benchmark state: a tagline while
// idle, an animated spinner with the active stage and elapsed time while
// running, and a finish flag when the results are ready.
export const useDocumentTitle = () => {
    const { state, activeBenchmark, results } = useBenchmarkQueue();

    let timer = null;
    let frame = 0;

    const stopTicker = () => {
        clearInterval(timer);
        timer = null;
    };

    const elapsed = () => {
        const startedAt = results[activeBenchmark.value].startedAt;

        return startedAt ? ` · ${formatClock(Date.now() - startedAt)}` : '';
    };

    const apply = () => {
        if( state.value === 'running' ) {
            if( !timer ) {
                timer = setInterval(apply, 250);
            }

            frame = (frame + 1) % FRAMES.length;
            document.title = `${FRAMES[frame]} ${STAGE_LABELS[activeBenchmark.value]}${elapsed()} — BenchKit`;

            return;
        }

        stopTicker();

        document.title = state.value === 'completed'
            ? '🏁 Results ready — BenchKit'
            : 'BenchKit — Understand true Laravel performance';
    };

    watch(state, apply, { immediate: true });
    onUnmounted(stopTicker);
};
