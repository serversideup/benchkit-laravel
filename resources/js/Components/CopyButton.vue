<template>
    <component :is="as" @click.stop="copy()" :type="as === 'button' ? 'button' : undefined" :role="as === 'button' ? undefined : 'button'"
        class="shrink-0 p-1.5 rounded-md cursor-pointer transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
        :class="copied ? 'text-[#47CD89]' : 'text-[#61656C] hover:text-[#CECFD2] hover:bg-[rgba(255,255,255,0.06)]'"
        :title="copied ? 'Copied' : label">
        <span class="sr-only">{{ copied ? 'Copied' : label }}</span>
        <IconCheck v-if="copied" />
        <IconClipboard v-else />
    </component>
</template>

<script setup>
import { ref } from 'vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconClipboard from '@/Components/Icons/IconClipboard.vue';
import { writeTextToClipboard } from '@/Composables/useClipboard';

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: 'Copy',
    },
    // Render as a span (role="button") when nested inside another button —
    // buttons cannot legally nest
    as: {
        type: String,
        default: 'button',
    },
});

const copied = ref(false);
let timer = null;

const copy = async () => {
    try {
        await writeTextToClipboard(props.text);
        copied.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => copied.value = false, 2000);
    } catch (error) {
        console.error(error);
    }
};
</script>
