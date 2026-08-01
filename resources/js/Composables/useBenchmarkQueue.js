import { ref, reactive } from 'vue';
import { useSettings } from '@/Composables/useSettings';
import { loadHostDetails } from '@/Composables/useHostDetails';
import { startRun, fetchRunLog, cancelRun, saveRun, dismissRun } from '@/Composables/useRunSession';
import { STAGES } from '@/stages';

const {
    form,
    activePreset,
} = useSettings();

const queue = STAGES.map(({ key }) => key);

// Which settings key enables which stage. The server holds the same map in
// app/Support/BenchmarkStages.php and is the one that acts on it — this
// copy exists only to preview what a run would include before it starts.
const enabledBy = {
    yabs: () => form.hardware,
    cfspeedtest: () => form.network,
    http: () => form.http,
    php: () => form.php_database,
};

const blankStage = () => ({
    output: [],
    status: 'pending',
    startedAt: null,
    endedAt: null,
});

const results = reactive(Object.fromEntries(queue.map((benchmark) => [benchmark, blankStage()])));

const activeBenchmark = ref('yabs');
const userViewingBenchmark = ref(null);
const viewingBenchmark = ref('yabs');

// idle · running · completed · interrupted — mirrors the server's run
// status rather than anything this tab decides for itself
const state = ref('idle');
const run = ref(null);
const startError = ref(null);

// Progress bars (cfspeedtest, fio) repaint a line in place with carriage
// returns rather than printing a new line each frame. In a terminal each
// \r returns the cursor to column 0 and following characters overwrite what
// is there; rendered verbatim in a <pre> those frames instead jam together
// on one line. Collapse each streamed line to what a terminal would finally
// show so the console reads cleanly.
const renderTerminalLine = (text) => {
    if( !text.includes('\r') ) {
        return text;
    }

    const buffer = [];
    let column = 0;

    for( const character of text ) {
        if( character === '\r' ) {
            column = 0;
        } else {
            buffer[column] = character;
            column++;
        }
    }

    return buffer.join('');
}

const appendOutput = (benchmark, output) => {
    const line = renderTerminalLine(output).trim();

    if( benchmark && results[benchmark] && line !== '' ) {
        results[benchmark].output.push(line);
    }
}

const timestamp = (value) => value ? Date.parse(value) : null;

// The console log is replayed from the beginning whenever this tab starts
// following a run it has not been watching — a fresh page load, a tab
// opened mid-run, a reload — so the scrollback is never lost.
let offset = 0;
let timer = null;
let polling = false;

// Whether this tab has adopted the run it is reading. An idle tab polls so
// it notices a run starting elsewhere, and without this it would also
// adopt the *previous* run — a finished record lingers on the server until
// the next run replaces it, and treating that as this tab's own run would
// send someone who just opened the start screen to an old result page.
let watching = false;

const clearOutput = () => {
    queue.forEach((benchmark) => Object.assign(results[benchmark], blankStage()));
};

const applyEvents = (events) => {
    events.forEach((event) => {
        if( event.type === 'out' || event.type === 'err' ) {
            appendOutput(event.stage, event.output);
        }

        // Long-running subjects can go a minute or more between output
        // lines — surface the heartbeat so the run never looks hung
        if( event.type === 'heartbeat' ) {
            appendOutput(event.stage, `... still running (${event.timestamp} UTC)`);
        }
    });
};

/**
 * Mirror the server's view of the run onto this tab. Stage status and
 * timing come from the run record rather than from the console events, so
 * a tab that joins late is in exactly the same state as one that watched
 * from the start.
 */
const applyRun = (payload) => {
    // A run already over before this tab started reading belongs to
    // whoever was watching it, not to us.
    if( !watching && payload?.status !== 'running' ) {
        run.value = null;
        state.value = 'idle';

        return;
    }

    run.value = payload;

    if( payload === null ) {
        watching = false;
        state.value = 'idle';

        return;
    }

    watching = true;

    queue.forEach((benchmark) => {
        const stage = payload.stages?.[benchmark];

        if( !stage ) {
            return;
        }

        results[benchmark].status = stage.status;
        results[benchmark].startedAt = timestamp(stage.started_at);
        results[benchmark].endedAt = timestamp(stage.ended_at);
    });

    const current = payload.current_stage ?? lastStageWithOutput() ?? queue[0];

    activeBenchmark.value = current;

    if( !userViewingBenchmark.value ) {
        viewingBenchmark.value = current;
    }

    state.value = {
        running: 'running',
        completed: 'completed',
        interrupted: 'interrupted',
        cancelled: 'idle',
    }[payload.status] ?? 'idle';

    // A cancelled run has nothing to show and nothing to save — drop it so
    // the app returns to its idle state in every tab watching.
    if( payload.status === 'cancelled' ) {
        forget();
    }
};

const lastStageWithOutput = () => [...queue].reverse().find((benchmark) => results[benchmark].output.length > 0) ?? null;

/**
 * Follow the run. Polling rather than an open stream: a run can be watched
 * from several places at once, and the web server load stage benchmarks
 * this very application — a held-open connection per viewer would be load
 * the measurement could not see.
 */
const poll = async () => {
    if( polling ) {
        return;
    }

    polling = true;

    try {
        const data = await fetchRunLog(offset);

        offset = data.offset;
        applyEvents(data.events);
        applyRun(data.run);
    } catch (error) {
        console.error(error);
    } finally {
        polling = false;
        schedule();
    }
};

// A live run is followed closely; an idle tab still checks in, so a run
// started somewhere else takes over this tab rather than leaving it
// offering a Start button that would be refused.
const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(poll, state.value === 'running' ? 1000 : 5000);
};

const follow = ({ replay = false } = {}) => {
    if( replay ) {
        offset = 0;
        clearOutput();
    }

    clearTimeout(timer);
    poll();
};

const unfollow = () => {
    clearTimeout(timer);
    timer = null;
};

const forget = async () => {
    watching = false;

    try {
        await dismissRun();
    } catch (error) {
        console.error(error);
    }

    run.value = null;
    state.value = 'idle';
    offset = 0;
    clearOutput();
    activeBenchmark.value = queue[0];
    userViewingBenchmark.value = null;
    viewingBenchmark.value = queue[0];
};

export const useBenchmarkQueue = () => {
    /**
     * Ask the server to start a run. If one is already going — started in
     * another tab, or on another machine — this tab joins it instead of
     * reporting a collision.
     */
    const startQueue = async () => {
        startError.value = null;
        offset = 0;
        clearOutput();

        try {
            const { run: payload } = await startRun(form.data(), activePreset.value, loadHostDetails());

            applyRun(payload);
        } catch (error) {
            if( error.data?.run ) {
                applyRun(error.data.run);
                follow({ replay: true });

                return;
            }

            startError.value = error.message;

            return;
        }

        follow();
    };

    const cancelQueue = async () => {
        try {
            applyRun((await cancelRun()).run);
        } catch (error) {
            console.error(error);
        }
    };

    const retrySave = async () => {
        try {
            applyRun((await saveRun()).run);
        } catch (error) {
            console.error(error);
        }
    };

    /**
     * Adopt the run the page was rendered with, then keep following it.
     * This is what puts a freshly loaded tab — in any browser — straight
     * into the live console instead of on the start screen.
     */
    const hydrate = (payload) => {
        if( payload ) {
            // The page was rendered with this run, so it is ours to follow
            // even if it has since stopped.
            watching = true;
            applyRun(payload);
            follow({ replay: true });

            return;
        }

        follow();
    };

    // Preview pending/skipped statuses while the user edits settings,
    // without touching a run that is already in progress
    const previewStatuses = () => {
        if( state.value !== 'idle' ) {
            return;
        }

        queue.forEach((benchmark) => {
            results[benchmark].status = enabledBy[benchmark]() ? 'pending' : 'skipped';
        });
    }

    return {
        queue,
        results,
        run,
        state,
        startError,
        activeBenchmark,
        userViewingBenchmark,
        viewingBenchmark,

        hydrate,
        unfollow,
        previewStatuses,
        startQueue,
        cancelQueue,
        retrySave,
        dismiss: forget,
    };
};
