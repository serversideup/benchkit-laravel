<template>
    <Background />

    <div class="flex flex-col fixed z-[99999] inset-0">
        <AppHeader class="shrink-0" />

        <main class="flex-1 min-h-0 w-full max-w-[1440px] mx-auto overflow-hidden flex flex-col">
            <slot />
        </main>
    </div>
</template>

<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import Background from '@/Components/Background.vue';
</script>