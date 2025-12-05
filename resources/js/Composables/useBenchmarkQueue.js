import { ref, reactive } from 'vue';

import { useStream } from '@/Composables/useStream';

const {
    startStream,
    stopStream,
} = useStream();

const queue = [
    'yabs',
    'cfspeedtest',
    'php'
];

const results = reactive({
    'yabs': {
        output: [],
        status: '',
        url: '/yabs'
    },
    'cfspeedtest': {
        output: [],
        status: '',
        url: '/cfspeedtest'
    },
    'php': {
        output: [],
        status: '',
        url: '/php'
    }
});

const activeBenchmark = ref('yabs');
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
        startStream(results[benchmark].url);
    }

    const nextBenchmark = () => {
        stopStream();

        if( queue.indexOf(activeBenchmark.value) + 1 < queue.length ) {
            let nextBenchmark = queue[queue.indexOf(activeBenchmark.value) + 1];
            startBenchmark(nextBenchmark);
        } else {
            state.value = 'completed';
        }
    }

    return {
        queue,
        results,
        activeBenchmark,
        state,

        appendOutput,
        setStatus,
        nextBenchmark,
        startBenchmark
    }
};