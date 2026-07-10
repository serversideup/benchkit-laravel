<template>
    <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-[100000]" @close="emit('close')">
            <TransitionChild
                as="template"
                enter="ease-out duration-200"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-150"
                leave-from="opacity-100"
                leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/80 backdrop-blur" aria-hidden="true" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel class="w-full max-w-md rounded-xl border border-[#22262F] bg-[#0C0E12] p-6 sm:p-7">
                            <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">{{ title }}</DialogTitle>
                            <p class="mt-2 text-sm text-[#94979C] leading-relaxed">{{ message }}</p>

                            <div class="mt-6 flex items-center justify-end gap-2.5">
                                <button @click="emit('close')" type="button" class="px-4 py-2.5 rounded-lg text-sm font-medium text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] hover:bg-[#13161B] hover:border-[#61656C] transition-colors duration-200 cursor-pointer">
                                    Cancel
                                </button>
                                <button @click="emit('confirm')" type="button" class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                                    {{ confirmLabel }}
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    confirmLabel: {
        type: String,
        default: 'Delete',
    },
});

const emit = defineEmits(['confirm', 'close']);
</script>
