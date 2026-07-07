import { watch, onUnmounted } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

const STAGE_LABELS = {
    yabs: 'Hardware',
    cfspeedtest: 'Network',
    http: 'Web Server',
    php: 'PHP',
};

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

        if( !startedAt ) {
            return '';
        }

        const seconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
        const pad = (n) => String(n).padStart(2, '0');

        return ` · ${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`;
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
