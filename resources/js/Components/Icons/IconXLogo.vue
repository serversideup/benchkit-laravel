<template>
    <svg xmlns="http://www.w3.org/2000/svg" :width="size" :height="size" viewBox="0 0 18 18" fill="none">
        <path d="M10.1909 7.41006L16.5656 0H15.055L9.51988 6.43405L5.09898 0H0L6.68527 9.72942L0 17.5H1.51068L7.35593 10.7054L12.0247 17.5H17.1237L10.1906 7.41006H10.1909ZM8.12184 9.81514L7.44449 8.84631L2.055 1.13722H4.37532L8.7247 7.3587L9.40206 8.32753L15.0557 16.4145H12.7354L8.12184 9.81551V9.81514Z" fill="currentColor"/>
    </svg>
</template>

<script setup>
defineProps({
    size: {
        type: [Number, String],
        default: 14,
    },
});
</script>
