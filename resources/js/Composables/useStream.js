import { ref } from 'vue';

const eventSource = ref(null);

export const useStream = () => {

    const startStream = () => {
        if (eventSource.value) {
            eventSource.value.close();
        }

        eventSource.value = new EventSource('https://benchkit.dev.test/events');

        eventSource.value.onmessage = (event) => {
            console.log(event.data);
        }

        eventSource.value.onerror = (event) => {
            console.error(event);
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
        stopStream,
    }
}