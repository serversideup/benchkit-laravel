<template>
    <svg xmlns="http://www.w3.org/2000/svg" :width="size" :height="size" viewBox="0 0 20 20" fill="none">
        <rect x="7.5" y="7.5" width="8.5" height="8.5" rx="1.5" stroke="currentColor" stroke-width="1.66667"/>
        <path d="M12.5 7.5V5.5C12.5 4.67157 11.8284 4 11 4H5.5C4.67157 4 4 4.67157 4 5.5V11C4 11.8284 4.67157 12.5 5.5 12.5H7.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round"/>
    </svg>
</template>

<script setup>
defineProps({
    size: {
        type: [Number, String],
        default: 16,
    },
});
</script>
