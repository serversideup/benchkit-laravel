<template>
    <svg xmlns="http://www.w3.org/2000/svg" :width="width" :height="height" viewBox="0 0 19 17" fill="none">
        <path d="M2.50016 11.8685C1.49517 11.1958 0.833496 10.0502 0.833496 8.75C0.833496 6.79702 2.32642 5.19274 4.23328 5.01614C4.62334 2.64344 6.6837 0.833332 9.16683 0.833332C11.65 0.833332 13.7103 2.64344 14.1004 5.01614C16.0072 5.19274 17.5002 6.79702 17.5002 8.75C17.5002 10.0502 16.8385 11.1958 15.8335 11.8685M5.8335 12.5L9.16683 15.8333M9.16683 15.8333L12.5002 12.5M9.16683 15.8333V8.33333" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</template>

<script setup>
defineProps({
    width: {
        type: [Number, String],
        default: 19,
    },
    height: {
        type: [Number, String],
        default: 17,
    },
});
</script>
