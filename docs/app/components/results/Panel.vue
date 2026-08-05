<template>
    <section class="py-9 border-t border-[#22262F] first:border-t-0 first:pt-2">
        <div v-if="$slots.aside" class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="text-base font-semibold text-[#F7F7F7]">{{ title }}</h2>
            <span class="flex flex-wrap items-center gap-2"><slot name="aside" /></span>
        </div>
        <h2 v-else class="text-base font-semibold text-[#F7F7F7]">{{ title }}</h2>
        <slot />
    </section>
</template>

<script setup lang="ts">
defineProps<{ title: string }>()
</script>
