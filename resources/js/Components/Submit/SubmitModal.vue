<template>
    <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-[99999]" @close="close">
            <TransitionChild
                as="template"
                enter="ease-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-200"
                leave-from="opacity-100"
                leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/80 backdrop-blur" aria-hidden="true" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel class="w-full max-w-md rounded-2xl border border-[#22262F] bg-[#0C0E12] p-6 sm:p-7">
                            <div class="flex items-center justify-between">
                                <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">Add your result to the gallery</DialogTitle>
                                <button class="p-2 cursor-pointer -mr-2 text-[#61656C] hover:text-[#CECFD2] transition-colors" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose :size="20" />
                                </button>
                            </div>

                            <p class="mt-2 text-sm text-[#94979C]">
                                Submitting <span class="text-[#CECFD2]">{{ run.meta?.label || 'this run' }}</span> &mdash;
                                your specs are detected automatically.
                            </p>

                            <!-- Host & plan: the one thing only the submitter knows, and what makes
                                 runs comparable. Auto-detected specs already ride along. -->
                            <div class="mt-5">
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="text-sm font-medium text-[#F7F7F7]">Where did this run? <span class="text-[#61656C] font-normal">&mdash; helps others compare</span></p>
                                    <span v-if="metaSaved" class="text-xs text-[#47CD89] shrink-0">Saved</span>
                                </div>
                                <HostDetailsFields class="mt-3" :host="host" :history="history" id-prefix="submit" />
                            </div>

                            <p class="mt-5 flex items-start gap-1.5 text-xs text-[#61656C] leading-relaxed">
                                <IconShield :size="14" class="shrink-0 mt-px" />
                                <span>A bot files this into the gallery, credited to your GitHub account. Your IP, domain, and logs are stripped out first.</span>
                            </p>

                            <button @click="submit" type="button"
                                class="mt-5 w-full rounded-lg py-3.5 flex items-center justify-center text-base font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                                Continue to GitHub
                                <IconArrowUpRight class="ml-2" :size="18" />
                            </button>

                            <p class="mt-6 pt-5 border-t border-[#22262F] text-sm text-[#94979C] text-center">
                                Prefer social? <button @click="$emit('share')" type="button" class="text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] cursor-pointer">Share on X instead &rarr;</button>
                            </p>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconArrowUpRight from '@/Components/Icons/IconArrowUpRight.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import { openSubmissionIssue } from '@/Composables/useSubmitResults';
import { useHostEditor } from '@/Composables/useHostDetails';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    run: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'share']);

// Host details autosave to the run silently, same as the share flow.
const {
    host,
    history,
    saved: metaSaved,
    seed,
} = useHostEditor({
    runId: () => props.run.id,
    active: () => props.open,
    onSaved: (meta) => Object.assign(props.run.meta, meta),
});

const runWithMeta = computed(() => ({
    ...props.run,
    meta: {
        ...props.run.meta,
        provider: host.provider || null,
        plan: host.plan || null,
        datacenter: host.datacenter || null,
        cost: host.cost || null,
    },
}));

watch(() => props.open, (open) => {
    if( open ) {
        seed(props.run.meta ?? {});
        metaSaved.value = false;
    }
});

const close = () => emit('close');

const submit = () => {
    openSubmissionIssue(runWithMeta.value);
    close();
};
</script>
