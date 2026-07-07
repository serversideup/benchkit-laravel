import { ref, reactive } from 'vue';
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
    php: {
        enabled: () => form.php_database,
        options: () => ({}),
    },
};

const results = reactive({
    'yabs': {
        output: [],
        status: 'pending',
        url: '/yabs'
    },
    'cfspeedtest': {
        output: [],
        status: 'pending',
        url: '/cfspeedtest'
    },
    'php': {
        output: [],
        status: 'pending',
        url: '/php'
    }
});

const activeBenchmark = ref('yabs');
const userViewingBenchmark = ref(null);
const viewingBenchmark = ref('yabs');
const state = ref('idle');

export const useBenchmarkQueue = () => {
    const appendOutput = (benchmark, output) => {
        results[benchmark].output.push(output);
    }

    const setStatus = (benchmark, status) => {
        results[benchmark].status = status;
    }

    const startBenchmark = (benchmark) => {
        state.value = 'running';
        results[benchmark].status = 'running';
        results[benchmark].output = [];
        activeBenchmark.value = benchmark;
        // If we are not viewing a benchmark, set the viewing benchmark to the active benchmark
        if( !userViewingBenchmark.value ) {
            viewingBenchmark.value = benchmark;
        }

        startStream(results[benchmark].url, stages[benchmark].options());
    }

    const resetResults = () => {
        results.yabs.output = [];
        results.yabs.status = 'pending';
        results.cfspeedtest.output = [];
        results.cfspeedtest.status = 'pending';
        results.php.output = [];
        results.php.status = 'pending';
    }

    // Start the queue from the first enabled stage, marking disabled stages as skipped
    const startQueue = () => {
        resetResults();

        for (const benchmark of queue) {
            if( stages[benchmark].enabled() ) {
                startBenchmark(benchmark);
                return;
            }

            results[benchmark].status = 'skipped';
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

            results[benchmark].status = 'skipped';
        }

        state.value = 'completed';
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
        nextBenchmark,
        startBenchmark,
        startQueue
    }
};
