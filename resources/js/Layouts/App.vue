<template>
    <Background />

    <div class="flex flex-col min-h-screen overflow-y-scroll fixed z-[99999] inset-0">
        <AppHeader />

        <main class="w-full max-w-[1440px] mx-auto">
            <slot />
        </main>
    </div>
</template>

<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import Background from '@/Components/Background.vue';
import { useStream } from '@/Composables/useStream';
import { onMounted, onUnmounted } from 'vue';

// const { startStream, stopStream } = useStream();

// onMounted(() => {
//     startStream();
// });

// onUnmounted(() => {
//     stopStream();
// });
</script>