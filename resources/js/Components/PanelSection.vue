<template>
    <section class="py-9">
        <div v-if="$slots.aside" class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="text-base font-semibold text-[#F7F7F7]">{{ title }}</h2>
            <slot name="aside" />
        </div>
        <h2 v-else class="text-base font-semibold text-[#F7F7F7]">{{ title }}</h2>

        <slot />
    </section>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>
