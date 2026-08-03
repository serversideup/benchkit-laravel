<template>
    <div v-if="status === 'pending'" class="w-full p-4 sm:p-8 rounded-lg border border-[#373A41] bg-[#13161B] overflow-y-auto">
        <pre
            class="text-xs font-mono text-white"
            v-text="'Waiting for other jobs to complete...'"/>
    </div>
    <div v-else-if="status === 'skipped'" class="w-full p-4 sm:p-8 rounded-lg border border-[#373A41] bg-[#13161B] overflow-y-auto">
        <pre
            class="text-xs font-mono text-white"
            v-text="'This benchmark has been skipped. Please check your settings and try again.'"/>
    </div>
    <div v-else ref="scroller" class="w-full p-4 sm:p-8 rounded-lg border border-[#373A41] bg-[#13161B] overflow-y-auto" :class="heightClass">
        <pre
            v-for="(line, index) in output"
            :key="index" class="text-xs font-mono text-white whitespace-pre-wrap break-words"
            v-text="line"/>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

defineProps({
    heightClass: {
        type: String,
        default: 'flex-1 min-h-0',
    },
});

const {
    results,
    viewingBenchmark,
} = useBenchmarkQueue();

const status = computed(() => results[viewingBenchmark.value].status);
const output = computed(() => results[viewingBenchmark.value].output);

const scroller = ref(null);

// Follow the tail like a CI log, but only while the user is already parked
// near the bottom — if they have scrolled up to read, leave them there.
watch(() => output.value.length, async () => {
    const el = scroller.value;

    if( !el ) {
        return;
    }

    const wasAtBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;

    await nextTick();

    if( wasAtBottom ) {
        el.scrollTop = el.scrollHeight;
    }
});
</script>
