import { ref, reactive } from 'vue';

const queue = reactive({
    'yabs': {
        output: []
    },
    'php': {
        output: []
    },
    'laravel': {
        output: []
    }
});

const activeBenchmark = ref('yabs');

export const useBenchmarkQueue = () => {
    const appendOutput = (benchmark, output) => {
        queue[benchmark].output.push(output);
    }

    return {
        queue,
        activeBenchmark,
        appendOutput
    }
};