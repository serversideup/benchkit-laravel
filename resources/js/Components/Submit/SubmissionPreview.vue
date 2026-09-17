<template>
    <div class="relative">
        <!-- The document itself, not a description of it. Anything derived
             from it here would be one more thing to keep true; this cannot go
             out of date, and "exactly what gets sent" is what the disclosure
             promised. -->
        <pre class="scroll-slim max-h-80 overflow-auto rounded-xl border border-[#22262F] bg-[#13161B] p-4 pr-12 font-mono text-xs leading-relaxed text-[#CECFD2]">{{ json }}</pre>

        <CopyButton :text="json" label="Copy this submission" class="absolute top-2.5 right-4 bg-[#13161B]" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import CopyButton from '@/Components/CopyButton.vue';

const props = defineProps({
    // The submission document from GET /runs/{id}/submission
    document: {
        type: Object,
        required: true,
    },
});

const json = computed(() => JSON.stringify(props.document, null, 2));
</script>
