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

        startStream(results[benchmark].url);
    }

    const resetResults = () => {
        results.yabs.output = [];
        results.yabs.status = 'pending';
        results.cfspeedtest.output = [];
        results.cfspeedtest.status = 'pending';
        results.php.output = [];
        results.php.status = 'pending';
    }

    const nextBenchmark = () => {
        stopStream();
        
        let activeIndex = queue.indexOf(activeBenchmark.value);

        if( activeIndex === 0 && form.network ) {
            if( form.network ) {
                startBenchmark('cfspeedtest');
            }else{
                activeBenchmark.value = 'cfspeedtest';
                nextBenchmark();
            }
        }else if( activeIndex === 1 && form.php_database ) {
            if( form.php_database ) {
                startBenchmark('php');
            }else{
                state.value = 'completed';
            }
        }else{
            state.value = 'completed';
        }
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
        startBenchmark
    }
};