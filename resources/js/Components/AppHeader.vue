<template>
    <header class="w-full flex items-center justify-between max-w-[1440px] py-5 px-8 mx-auto">
        <div class="flex flex-1">
            <BenchkitLogo size-class="h-8" />
        </div>

        <div class="flex items-center justify-center w-[700px]">
            <a href="https://getspin.pro/" target="_blank" class="px-4 py-2 rounded-full bg-[rgba(87,19,10,0.80)] flex items-center text-xs text-[#CECFD2] font-mono hover:bg-[rgba(87,19,10,0.80)]/80 transition-all duration-300 cursor-pointer">
                <img src="/images/logos/spin-pro.svg" alt="Spin Pro" class="h-5 mr-3">

                100% replicated Laravel environments. Any VPS. Any OS.
            </a>
        </div>

        <div class="flex items-center gap-2 flex-1">
            <a class="px-3 py-2 font-mono inline-flex items-center text-[#CECFD2] text-sm bg-[#0C0E12] rounded-lg border border-[#373A41] hover:bg-[#0C0E12]/80 transition-all duration-300 cursor-pointer" href="https://serversideup.net/discord" target="_blank">
                <img src="/images/logos/discord-icon.svg" alt="Discord" class="h-5 mr-1"/>
                Join Discord
            </a>

            <a class="px-3 py-2 font-mono inline-flex items-center text-[#414651] text-sm bg-white rounded-lg border border-[#D5D7DA] hover:bg-white/90 transition-all duration-300 cursor-pointer" href="https://github.com/serversideup/benchkit-laravel" target="_blank">
                <img src="/images/logos/github-icon.svg" alt="GitHub" class="h-5 mr-1"/>
                Star on GitHub
            </a>
        </div>
    </header>

    <SettingsDrawer
        :open="isOpen"
        @close="close"
    />
</template>

<script setup>
import BenchkitLogo from '@/Components/BenchkitLogo.vue';
import SettingsDrawer from '@/Components/SettingsDrawer.vue';
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const { isOpen, close } = useSettingsDrawer();
</script>