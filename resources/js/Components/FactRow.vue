<template>
    <div class="flex font-mono mt-1 text-xs text-white h-5">
        <label :class="{ 'text-[#66C61C]': heading }">{{ label }}</label>
        <div class="flex-1 truncate text-clip mx-2.5">{{ ".".repeat(200) }}</div>
        <div v-if="$slots.default" class="flex items-center" :class="valueClass">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    // Section headers are green labels with a dotted leader and no value
    heading: {
        type: Boolean,
        default: false,
    },
    valueClass: {
        type: [String, Object, Array],
        default: '',
    },
});
</script>
