<template>
    <div class="flex flex-col">
        <div class="flex items-center">
            <Switch
                v-model="model"
                :class="model ? 'bg-[#E62E05]' : 'bg-transparent border border-[#373A41]'"
                class="relative inline-flex h-[22px] w-9 shrink-0 cursor-pointer rounded-full border-2 border-[#373A41] transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75">
                <span class="sr-only">Enable {{ label }}</span>
                <span
                    aria-hidden="true"
                    :class="model ? 'translate-x-[15px]' : 'translate-x-0'"
                    class="pointer-events-none inline-block mt-px h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                />
            </Switch>
            <label class="ml-2.5 text-sm text-[#F7F7F7] font-mono">{{ label }}</label>
        </div>
        <p class="mt-2 text-sm text-[#94979C] font-mono">
            <slot />
        </p>
    </div>
</template>

<script setup>
import { Switch } from '@headlessui/vue';

const model = defineModel({ type: Boolean });

defineProps({
    label: {
        type: String,
        required: true,
    },
});
</script>
