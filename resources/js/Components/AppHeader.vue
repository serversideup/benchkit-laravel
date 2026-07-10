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
            <a :href="promo.href" target="_blank" class="hidden xl:flex shrink min-w-0 px-4 py-2 rounded-full bg-[rgba(87,19,10,0.80)] hover:bg-[rgba(87,19,10,1)] items-center text-xs text-[#CECFD2] hover:text-[#F7F7F7] font-mono transition-colors duration-300 cursor-pointer"
                @mouseenter="pausePromos" @mouseleave="resumePromos">
                <span :key="promo.name" class="promo-in flex items-center min-w-0">
                    <img :src="promo.logo" :alt="promo.name" class="h-5 shrink-0">
                    <span class="ml-3 truncate">{{ promo.tagline }}</span>
                </span>
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
             sticky glass, so it never scrolls awkwardly under the header. -->
        <a :href="promo.href" target="_blank" class="xl:hidden flex items-center justify-center w-full px-4 py-2 bg-[rgba(87,19,10,0.80)] hover:bg-[rgba(87,19,10,1)] border-b border-white/5 text-[#CECFD2] hover:text-[#F7F7F7] transition-colors duration-300 cursor-pointer"
            @mouseenter="pausePromos" @mouseleave="resumePromos">
            <span :key="promo.name" class="promo-in flex items-center justify-center gap-2.5 min-w-0">
                <img :src="promo.logo" :alt="promo.name" class="h-4 shrink-0">
                <span class="text-xs font-mono truncate">{{ promo.tagline }}</span>
            </span>
        </a>
    </div>

    <SettingsDrawer
        :open="isOpen"
        @close="close"
    />
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useIntervalFn, useMediaQuery, useWindowScroll } from '@vueuse/core';
import BenchkitLogo from '@/Components/BenchkitLogo.vue';
import SettingsDrawer from '@/Components/SettingsDrawer.vue';
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const { isOpen, close } = useSettingsDrawer();

const { y } = useWindowScroll();
const scrolled = computed(() => y.value > 8);

// Rotated through the header promo spot. Taglines come from each product's
// short_description in the serversideup site content.
const promos = [
    {
        name: 'Spin Pro',
        href: 'https://getspin.pro/',
        logo: '/images/logos/spin-pro.svg',
        tagline: '100% replicated Laravel environments. Any VPS. Any OS.',
    },
    {
        name: 'Bugflow',
        href: 'https://bugflow.io',
        logo: '/images/logos/bugflow.svg',
        tagline: 'Visual product feedback, sent directly to GitHub, GitLab, and more.',
    },
    {
        name: 'Self-Host Pro',
        href: 'https://selfhostpro.com',
        logo: '/images/logos/selfhostpro.svg',
        tagline: 'Sell self-hosted software in minutes.',
    },
];

// Lead with a random product each page load so short sessions still spread
// exposure across all three; reduced-motion visitors keep that pick statically.
const promoIndex = ref(Math.floor(Math.random() * promos.length));
const promo = computed(() => promos[promoIndex.value]);

const prefersReducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');

const { pause: pausePromos, resume } = useIntervalFn(() => {
    promoIndex.value = (promoIndex.value + 1) % promos.length;
}, 12000, { immediate: !prefersReducedMotion.value });

const resumePromos = () => {
    if (!prefersReducedMotion.value) {
        resume();
    }
};
</script>

<style scoped>
/* Same easing family as Index.vue's state-in; keyed on the product name so
   each rotation softly settles in rather than hard-swapping. */
@keyframes promo-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.promo-in {
    animation: promo-in 0.4s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}
</style>
