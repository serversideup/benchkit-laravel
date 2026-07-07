import { ref } from 'vue';
import { useEventBus } from '@vueuse/core';

const abortController = ref(null);

const streamEventBus = useEventBus('stream-event-bus');

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

export const useStream = () => {

    const startStream = async (url, options = {}) => {
        stopStream();

        abortController.value = new AbortController();

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(options),
                signal: abortController.value.signal,
            });

            if (!response.ok) {
                const error = response.status === 409
                    ? 'Another benchmark is already running.'
                    : `Benchmark request failed (HTTP ${response.status}).`;

                streamEventBus.emit('benchmark:output', JSON.stringify({ status: 'error', error }));
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                let separatorIndex;
                while ((separatorIndex = buffer.indexOf('\n\n')) !== -1) {
                    const rawEvent = buffer.slice(0, separatorIndex);
                    buffer = buffer.slice(separatorIndex + 2);

                    rawEvent.split('\n').forEach((line) => {
                        if (line.startsWith('data: ')) {
                            streamEventBus.emit('benchmark:output', line.slice(6));
                        }
                    });
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
                streamEventBus.emit('benchmark:output', JSON.stringify({ status: 'error', error: error.message }));
            }
        }
    }

    const stopStream = () => {
        if (abortController.value) {
            abortController.value.abort();
            abortController.value = null;
        }
    }

    return {
        startStream,
        stopStream
    }
}
