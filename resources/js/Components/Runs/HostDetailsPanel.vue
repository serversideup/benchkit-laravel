<template>
    <div class="w-full rounded-xl border border-[#22262F] bg-[#0C0E12] p-4 sm:p-5">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-[#F7F7F7]">Hosting details</p>
                <p class="text-xs text-[#94979C] mt-0.5">Saved to this run &mdash; prefilled on your next one.</p>
            </div>
            <span class="flex items-baseline gap-4 shrink-0 text-sm">
                <span v-if="saved" class="text-xs text-[#47CD89]">Saved</span>
                <button v-if="hasAnyValue" @click="confirmingClear = true" type="button" class="text-[#94979C] hover:text-[#F97066] cursor-pointer transition-colors duration-200">Clear</button>
                <button @click="emit('close')" type="button" class="text-[#94979C] hover:text-[#CECFD2] cursor-pointer">Done</button>
            </span>
        </div>

        <HostDetailsFields class="mt-4" grid-class="lg:grid-cols-3" :host="host" :history="history" id-prefix="host" />

        <ConfirmModal :open="confirmingClear"
            title="Clear hosting details?"
            message="This removes the host, plan, datacenter, and cost from this run — and from the prefill for your next one. Your autocomplete suggestions are kept."
            confirm-label="Clear details"
            @confirm="confirmClear()" @close="confirmingClear = false" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import { useHostEditor } from '@/Composables/useHostDetails';

const props = defineProps({
    runId: {
        type: String,
        required: true,
    },
    meta: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['updated', 'close']);

const { host, history, saved, hasAnyValue, clearHost } = useHostEditor({
    runId: props.runId,
    meta: props.meta,
    onSaved: (meta) => emit('updated', meta),
});

const confirmingClear = ref(false);

const confirmClear = () => {
    clearHost();
    confirmingClear.value = false;
};
</script>
