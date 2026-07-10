import { ref, reactive } from 'vue';
import { useEventBus } from '@vueuse/core';
import { useStream } from '@/Composables/useStream';
import { useSettings } from '@/Composables/useSettings';

const {
    form
} = useSettings();

const {
    startStream,
    stopStream,
} = useStream();

const queue = [
    'yabs',
    'cfspeedtest',
    'http',
    'php'
];

const stages = {
    yabs: {
        enabled: () => form.hardware,
        options: () => ({
            disk: form.disk,
            geekbench: form.geekbench,
            geekbench_version: form.geekbench_version,
            iperf: form.iperf,
        }),
    },
    cfspeedtest: {
        enabled: () => form.network,
        options: () => ({
            network_test_type: form.network_test_type,
        }),
    },
    http: {
        enabled: () => form.http,
        options: () => ({
            duration: form.http_duration,
            connections: form.http_connections,
        }),
    },
    php: {
        enabled: () => form.php_database,
        options: () => ({ mode: form.php_mode }),
    },
};

const results = reactive({
    'yabs': {
        output: [],
        status: 'pending',
        url: '/yabs',
        startedAt: null,
        endedAt: null
    },
    'cfspeedtest': {
        output: [],
        status: 'pending',
        url: '/cfspeedtest',
        startedAt: null,
        endedAt: null
    },
    'http': {
        output: [],
        status: 'pending',
        url: '/http',
        startedAt: null,
        endedAt: null
    },
    'php': {
        output: [],
        status: 'pending',
        url: '/php',
        startedAt: null,
        endedAt: null
    }
});

const activeBenchmark = ref('yabs');
const userViewingBenchmark = ref(null);
const viewingBenchmark = ref('yabs');
const state = ref('idle');

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

export const useBenchmarkQueue = () => {
    const appendOutput = (benchmark, output) => {
        const line = renderTerminalLine(output).trim();

        if( line !== '' ) {
            results[benchmark].output.push(line);
        }
    }

    const setStatus = (benchmark, status) => {
        results[benchmark].status = status;

        if( status === 'completed' || status === 'error' ) {
            results[benchmark].endedAt = Date.now();
        }
    }

    const startBenchmark = (benchmark) => {
        state.value = 'running';
        results[benchmark].status = 'running';
        results[benchmark].output = [];
        results[benchmark].startedAt = Date.now();
        results[benchmark].endedAt = null;
        activeBenchmark.value = benchmark;
        // If we are not viewing a benchmark, set the viewing benchmark to the active benchmark
        if( !userViewingBenchmark.value ) {
            viewingBenchmark.value = benchmark;
        }

        startStream(results[benchmark].url, stages[benchmark].options());
    }

    // Disabled stages are marked skipped up front so they never show the
    // amber "pending" icon while the queue runs
    const resetResults = () => {
        queue.forEach((benchmark) => {
            results[benchmark].output = [];
            results[benchmark].status = stages[benchmark].enabled() ? 'pending' : 'skipped';
            results[benchmark].startedAt = null;
            results[benchmark].endedAt = null;
        });
    }

    // Preview pending/skipped statuses while the user edits settings,
    // without touching a run that is already in progress
    const previewStatuses = () => {
        if( state.value !== 'idle' ) {
            return;
        }

        queue.forEach((benchmark) => {
            results[benchmark].status = stages[benchmark].enabled() ? 'pending' : 'skipped';
        });
    }

    // Start the queue from the first enabled stage
    const startQueue = () => {
        resetResults();

        for (const benchmark of queue) {
            if( stages[benchmark].enabled() ) {
                startBenchmark(benchmark);
                return;
            }
        }

        state.value = 'completed';
    }

    const nextBenchmark = () => {
        stopStream();

        const activeIndex = queue.indexOf(activeBenchmark.value);

        for (let i = activeIndex + 1; i < queue.length; i++) {
            const benchmark = queue[i];

            if( stages[benchmark].enabled() ) {
                startBenchmark(benchmark);
                return;
            }
        }

        state.value = 'completed';
    }

    // Aborting the stream disconnects the SSE request, which the server
    // detects within a second and kills the running benchmark subprocess
    const cancelQueue = () => {
        stopStream();
        resetResults();
        state.value = 'idle';
        activeBenchmark.value = 'yabs';
        userViewingBenchmark.value = null;
        viewingBenchmark.value = 'yabs';
    }

    return {
        queue,
        results,
        activeBenchmark,
        userViewingBenchmark,
        viewingBenchmark,
        state,

        appendOutput,
        setStatus,
        resetResults,
        previewStatuses,
        nextBenchmark,
        startBenchmark,
        startQueue,
        cancelQueue
    }
};

// Stream events drive the queue from module scope, not from a component:
// tying this listener to a component lifecycle would hang the run if the
// user navigated to another page (e.g. run history) mid-benchmark
const streamEventBus = useEventBus('stream-event-bus');

streamEventBus.on((message, data) => {
    if( message !== 'benchmark:output' ) {
        return;
    }

    const { appendOutput, setStatus, nextBenchmark } = useBenchmarkQueue();
    const event = JSON.parse(data);

    if( event.type === 'out' || event.type === 'err' ) {
        appendOutput(activeBenchmark.value, event.output);
    }

    // Long-running subjects can go a minute or more between output lines —
    // surface the server heartbeat so the run never looks hung
    if( event.type === 'heartbeat' ) {
        appendOutput(activeBenchmark.value, `... still running (${event.timestamp} UTC)`);
    }

    if( event.status === 'completed' ) {
        setStatus(activeBenchmark.value, 'completed');
        nextBenchmark();
    }

    if( event.status === 'error' ) {
        setStatus(activeBenchmark.value, 'error');
        nextBenchmark();
    }
});
