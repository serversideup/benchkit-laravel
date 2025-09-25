import { ref } from 'vue';
import { useEventBus } from '@vueuse/core';

const eventSource = ref(null);

const streamEventBus = useEventBus('stream-event-bus');

export const useStream = () => {

    const startStream = ( url ) => {
        if (eventSource.value) {
            eventSource.value.close();
        }

        eventSource.value = new EventSource(url);

        eventSource.value.onmessage = (event) => {
            streamEventBus.emit('benchmark:output', event.data);
        }

        eventSource.value.onerror = (event) => {
            console.error(eventSource.value,event);
        }

        eventSource.value.onopen = (event) => {
            console.log(event);
        }
    }

    const stopStream = () => {
        if (eventSource.value) {
            eventSource.value.close();
            eventSource.value = null;
        }
    }

    return {
        eventSource,
        startStream,
        stopStream
    }
}