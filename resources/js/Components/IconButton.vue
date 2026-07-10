<template>
    <Tooltip :label="label">
        <button @click="emit('click')" type="button" :aria-label="label"
            class="p-2.5 rounded-lg text-[#94979C] hover:text-[#F7F7F7] hover:bg-[rgba(255,255,255,0.07)] transition-colors duration-200 cursor-pointer">
            <slot />
        </button>
    </Tooltip>
</template>

<script setup>
import Tooltip from '@/Components/Tooltip.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['click']);
</script>
