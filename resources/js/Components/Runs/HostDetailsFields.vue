<template>
    <div class="grid grid-cols-2 gap-3.5">
        <div v-for="field in HOST_FIELDS" :key="field.key" class="flex flex-col gap-1">
            <label :for="`${idPrefix}-${field.key}`" class="text-xs text-[#94979C]">{{ field.label }}</label>
            <input :id="`${idPrefix}-${field.key}`" v-model="host[field.key]" type="text" :maxlength="field.max" :placeholder="field.placeholder" :list="`${idPrefix}-${field.key}-options`"
                class="rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 text-sm text-[#F7F7F7] font-mono placeholder:text-[#61656C] focus:outline-none focus:border-[#61656C]">
            <datalist :id="`${idPrefix}-${field.key}-options`">
                <option v-for="entry in history[field.key]" :key="entry" :value="entry" />
            </datalist>
        </div>
    </div>
</template>

<script setup>
import { HOST_FIELDS } from '@/Composables/useHostDetails';

defineProps({
    // The reactive host object from useHostEditor — inputs bind straight
    // into it
    host: {
        type: Object,
        required: true,
    },
    history: {
        type: Object,
        required: true,
    },
    // Keeps input/datalist ids unique when two editors exist in the DOM
    idPrefix: {
        type: String,
        required: true,
    },
});
</script>
