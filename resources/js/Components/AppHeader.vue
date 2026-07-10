<template>
    <!-- Transparent while at the top so the header belongs to the animated
         canvas; the dark glass fades in only once content scrolls beneath it -->
    <div class="sticky top-0 z-40 w-full transition-all duration-300"
        :class="scrolled ? 'bg-[#1a0f0f]/75 backdrop-blur-md border-b border-white/5' : 'bg-transparent backdrop-blur-none border-b border-transparent'">
        <header class="w-full h-[72px] flex items-center gap-4 max-w-[1440px] px-4 sm:px-8 mx-auto">
            <div class="flex flex-1 items-center min-w-0">
                <Link href="/" class="shrink-0">
                    <BenchkitLogo size-class="h-8" />
                </Link>
            </div>

            <!-- The promo only rides in the header when its full pitch fits —
                 a logo-only pill reads as confusing, so below xl it moves to
                 the banner instead -->
            <a href="https://getspin.pro/" target="_blank" class="hidden xl:flex shrink min-w-0 px-4 py-2 rounded-full bg-[rgba(87,19,10,0.80)] hover:bg-[rgba(87,19,10,1)] items-center text-xs text-[#CECFD2] hover:text-[#F7F7F7] font-mono transition-colors duration-300 cursor-pointer">
                <img src="/images/logos/spin-pro.svg" alt="Spin Pro" class="h-5 shrink-0">
                <span class="ml-3 truncate">100% replicated Laravel environments. Any VPS. Any OS.</span>
            </a>

            <div class="flex flex-1 items-center justify-end gap-2 shrink-0">
                <a class="px-3 py-2 inline-flex items-center whitespace-nowrap text-[#CECFD2] text-sm font-medium bg-[#0C0E12] rounded-lg border border-[#373A41] hover:bg-[#0C0E12]/80 transition-all duration-300 cursor-pointer" href="https://serversideup.net/discord" target="_blank">
                    <img src="/images/logos/discord-icon.svg" alt="Discord" class="h-5"/>
                    <span class="ml-1.5">Join<span class="hidden xl:inline"> Discord</span></span>
                </a>

                <a class="px-3 py-2 inline-flex items-center whitespace-nowrap text-[#414651] text-sm font-medium bg-white rounded-lg border border-[#D5D7DA] hover:bg-white/90 transition-all duration-300 cursor-pointer" href="https://github.com/serversideup/benchkit-laravel" target="_blank">
                    <img src="/images/logos/github-icon.svg" alt="GitHub" class="h-5"/>
                    <span class="ml-1.5">Star<span class="hidden xl:inline"> on GitHub</span></span>
                </a>
            </div>
        </header>

        <!-- Below xl the promo becomes a full-width banner riding inside the
             sticky glass, so it never scrolls awkwardly under the header.
             One anchor, easy to rotate products through later. -->
        <a href="https://getspin.pro/" target="_blank" class="xl:hidden flex items-center justify-center gap-2.5 w-full px-4 py-2 bg-[rgba(87,19,10,0.80)] hover:bg-[rgba(87,19,10,1)] border-b border-white/5 text-[#CECFD2] hover:text-[#F7F7F7] transition-colors duration-300 cursor-pointer">
            <img src="/images/logos/spin-pro.svg" alt="Spin Pro" class="h-4 shrink-0">
            <span class="text-xs font-mono truncate">100% replicated Laravel environments. Any VPS. Any OS.</span>
        </a>
    </div>

    <SettingsDrawer
        :open="isOpen"
        @close="close"
    />
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';
import BenchkitLogo from '@/Components/BenchkitLogo.vue';
import SettingsDrawer from '@/Components/SettingsDrawer.vue';
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const { isOpen, close } = useSettingsDrawer();

const { y } = useWindowScroll();
const scrolled = computed(() => y.value > 8);
</script>
