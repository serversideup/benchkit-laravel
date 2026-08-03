<template>
    <Background />

    <!-- Normal document flow: the body is the scroll container, so the
         scrollbar spans the full window like any website. Only the Running
         console bounds itself to the viewport (h-[calc(100dvh-72px)]). -->
    <!-- overflow-x-clip: absolutely-positioned bits that sit past the right
         edge (hover tooltips on status dots, centered + whitespace-nowrap)
         otherwise extend the page's scroll width and let the whole thing
         scroll sideways on mobile. Clip only the horizontal axis so those
         tooltips still drop down normally on desktop; fixed layers
         (Background, SettingsDrawer) are unaffected. -->
    <div class="relative z-10 min-h-dvh flex flex-col overflow-x-clip">
        <AppHeader />

        <main class="flex-1 w-full max-w-[1440px] mx-auto flex flex-col">
            <slot />
        </main>
    </div>
</template>

<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import Background from '@/Components/Background.vue';
</script>