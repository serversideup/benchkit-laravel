<template>
    <div class="flex flex-col min-w-0">
        <div v-if="!editingLabel" class="flex items-center gap-2.5 min-w-0 group">
            <h1 @click="startEditingLabel()" title="Rename run"
                class="text-3xl sm:text-4xl text-[#F7F7F7] font-semibold tracking-[-0.03em] leading-[1.1] truncate cursor-text rounded-lg -mx-2 px-2 -my-1 py-1 hover:bg-[rgba(255,255,255,0.05)] transition-colors duration-200">{{ meta.label }}</h1>
            <button @click="startEditingLabel()" type="button" class="shrink-0 p-1.5 rounded-md text-[#61656C] hover:text-[#CECFD2] hover:bg-[rgba(255,255,255,0.06)] cursor-pointer" title="Rename run">
                <span class="sr-only">Rename run</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <path d="M14.166 2.5A2.357 2.357 0 0 1 17.5 5.833L6.25 17.083l-4.583 1.25 1.25-4.583L14.166 2.5Z" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <form v-else @submit.prevent="saveLabel()" class="flex items-center gap-2.5 w-full max-w-xl">
            <input ref="labelInput" v-model="draftLabel" type="text" maxlength="120" aria-label="Run label" @keydown.esc="editingLabel = false"
                class="flex-1 rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 text-xl text-[#F7F7F7] font-mono focus:outline-none focus:border-[#61656C]">
            <button type="submit" :disabled="labelSaving" class="px-3.5 py-2 rounded-lg text-sm font-medium text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer disabled:opacity-50">
                {{ labelSaving ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" @click="editingLabel = false" class="px-3.5 py-2 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] cursor-pointer">
                Cancel
            </button>
        </form>

    </div>
</template>

<script setup>
import { nextTick, ref } from 'vue';
import { updateRunMeta } from '@/Composables/useRunActions';

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

const emit = defineEmits(['updated']);

const editingLabel = ref(false);
const labelSaving = ref(false);
const draftLabel = ref('');
const labelInput = ref(null);

const startEditingLabel = async () => {
    draftLabel.value = props.meta.label ?? '';
    editingLabel.value = true;

    await nextTick();
    labelInput.value?.focus();
    labelInput.value?.select();
};

const saveLabel = async () => {
    labelSaving.value = true;

    try {
        const run = await updateRunMeta(props.runId, { label: draftLabel.value });
        emit('updated', run.meta);
        editingLabel.value = false;
    } catch (error) {
        console.error(error);
    } finally {
        labelSaving.value = false;
    }
};
</script>
