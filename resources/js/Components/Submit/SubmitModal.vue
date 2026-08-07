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
                                <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">
                                    {{ reviewing ? 'Review what gets published' : 'Add your result to the gallery' }}
                                </DialogTitle>
                                <button class="p-2 cursor-pointer -mr-2 text-[#61656C] hover:text-[#CECFD2] transition-colors" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose :size="20" />
                                </button>
                            </div>

                            <!-- Step 1: host & plan, the one thing only the submitter knows,
                                 and what makes runs comparable. Auto-detected specs already
                                 ride along. -->
                            <template v-if="!reviewing">
                                <p class="mt-2 text-sm text-[#94979C]">
                                    Submitting <span class="text-[#CECFD2]">{{ run.meta?.label || 'this run' }}</span> &mdash;
                                    your specs are detected automatically.
                                </p>

                                <div class="mt-5">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <p class="text-sm font-medium text-[#F7F7F7]">Where did this run? <span class="text-[#61656C] font-normal">&mdash; helps others compare</span></p>
                                        <span v-if="metaSaved" class="text-xs text-[#47CD89] shrink-0">Saved</span>
                                    </div>
                                    <HostDetailsFields class="mt-3" :host="host" :history="history" id-prefix="submit" />
                                </div>

                                <button @click="review" type="button"
                                    class="mt-5 w-full rounded-lg py-3.5 flex items-center justify-center text-base font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer">
                                    Continue
                                    <IconArrowUpRight class="ml-2" :size="18" />
                                </button>

                                <p class="mt-6 pt-5 border-t border-[#22262F] text-sm text-[#94979C] text-center">
                                    Prefer social? <button @click="$emit('share')" type="button" class="text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] cursor-pointer">Share on X instead &rarr;</button>
                                </p>
                            </template>

                            <!-- Step 2: the document as the gallery will hold it, so nothing
                                 about this is a surprise after the fact. -->
                            <template v-else>
                                <p class="mt-2 text-sm text-[#94979C]">
                                    This is everything BenchKit will send. Nothing leaves your machine until you continue.
                                </p>

                                <div v-if="loading" class="mt-5 h-64 rounded-xl border border-[#22262F] bg-[#13161B] animate-pulse" />

                                <div v-else-if="error" class="mt-5 rounded-xl border border-[#373A41] bg-[#13161B] px-4 py-3">
                                    <p class="text-sm text-[#F7F7F7]">Couldn't prepare the submission</p>
                                    <p class="mt-1 text-xs text-[#94979C]">{{ error }}</p>
                                    <button @click="review" type="button" class="mt-3 text-xs text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] cursor-pointer">Try again</button>
                                </div>

                                <SubmissionPreview v-else-if="submission" class="mt-5" :document="submission.document" />

                                <!-- Only shown when the token is too large to ride in the URL.
                                     It is copied either way, but promising a one-click file
                                     when the submitter has to paste would be a lie. -->
                                <p v-if="submission && !submission.prefill" class="mt-4 flex items-start gap-1.5 text-xs text-[#F79009] leading-relaxed">
                                    <IconShield :size="14" class="shrink-0 mt-px" />
                                    <span>This result is too large to pre-fill. It's been copied to your clipboard &mdash; paste it into the code block GitHub opens.</span>
                                </p>

                                <button @click="submit" type="button" :disabled="!submission"
                                    class="mt-5 w-full rounded-lg py-3.5 flex items-center justify-center text-base font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] disabled:opacity-40 disabled:cursor-not-allowed transition-colors duration-200 cursor-pointer">
                                    Continue to GitHub
                                    <IconArrowUpRight class="ml-2" :size="18" />
                                </button>

                                <p class="mt-3 text-xs text-[#61656C] text-center leading-relaxed">
                                    A bot files this into the gallery, credited to your GitHub account.
                                </p>

                                <p class="mt-5 pt-5 border-t border-[#22262F] text-sm text-[#94979C] text-center">
                                    <button @click="reviewing = false" type="button" class="text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] cursor-pointer">&larr; Back to host details</button>
                                </p>
                            </template>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconArrowUpRight from '@/Components/Icons/IconArrowUpRight.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import SubmissionPreview from '@/Components/Submit/SubmissionPreview.vue';
import { copyToken, fetchSubmission, openIssue } from '@/Composables/useSubmitResults';
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

const reviewing = ref(false);
const loading = ref(false);
const error = ref(null);
const submission = ref(null);

// Host details autosave to the run silently, same as the share flow.
const {
    host,
    history,
    saved: metaSaved,
    seed,
    flush,
} = useHostEditor({
    runId: () => props.run.id,
    active: () => props.open,
    onSaved: (meta) => Object.assign(props.run.meta, meta),
});

watch(() => props.open, (open) => {
    if( open ) {
        seed(props.run.meta ?? {});
        metaSaved.value = false;
        reviewing.value = false;
        submission.value = null;
        error.value = null;
    }
});

/**
 * The server builds the submission from the *stored* run, so a host detail
 * still sitting in the autosave debounce has to be committed first or it
 * simply wouldn't be in the document.
 */
const review = async () => {
    reviewing.value = true;
    loading.value = true;
    error.value = null;

    try {
        await flush();
        submission.value = await fetchSubmission(props.run.id);
    } catch (problem) {
        error.value = problem.message;
    } finally {
        loading.value = false;
    }
};

const close = () => emit('close');

// Both the clipboard write and the popup want a live user gesture, so neither
// may be awaited first — the document was already fetched when this step
// opened, so there is nothing to wait for anyway.
const submit = () => {
    copyToken(submission.value.token);
    openIssue(submission.value.issue_url);
    close();
};
</script>
