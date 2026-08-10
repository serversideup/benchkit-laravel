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
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">Add your result to the gallery</DialogTitle>
                                    <!-- Which run, stated as its numbers: the identity that
                                         matters here isn't the label, it's the result. -->
                                    <p class="mt-1 truncate font-mono text-xs text-[#61656C] tabular-nums">{{ identity }}</p>
                                </div>
                                <button class="-mt-1 -mr-2 shrink-0 cursor-pointer p-2 text-[#61656C] transition-colors hover:text-[#CECFD2]" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose :size="20" />
                                </button>
                            </div>

                            <!-- The only thing on this screen that needs a decision. Everything
                                 below it is confirmation, so it gets the top of the modal and
                                 the only visible inputs. -->
                            <div class="mt-6">
                                <div class="flex items-baseline justify-between gap-3">
                                    <h3 class="text-[11px] font-medium tracking-wide text-[#94979C] uppercase">Where did this run?</h3>
                                    <Transition
                                        enter-active-class="transition duration-200" enter-from-class="opacity-0"
                                        leave-active-class="transition duration-300" leave-to-class="opacity-0">
                                        <span v-if="metaSaved" class="shrink-0 text-xs text-[#47CD89]">Saved</span>
                                    </Transition>
                                </div>
                                <HostDetailsFields class="mt-3" :host="host" :history="history" id-prefix="submit" />
                            </div>

                            <div class="mt-6 border-t border-[#22262F] pt-5">
                                <p class="flex items-start gap-2 text-xs leading-relaxed text-[#94979C]">
                                    <IconShield :size="14" class="mt-px shrink-0 text-[#47CD89]" />
                                    <span>Your specs, settings, and results are published. Your IP, domain, and logs are stripped out first.</span>
                                </p>

                                <!-- Progressive disclosure rather than a second step: the
                                     detail is one click away for anyone who wants it, and
                                     costs nothing to everyone who doesn't. -->
                                <button type="button" :disabled="!submission" :aria-expanded="expanded"
                                    class="mt-2.5 -mx-1 flex w-[calc(100%+0.5rem)] cursor-pointer items-center gap-1.5 rounded px-1 py-1 text-xs text-[#CECFD2] transition-colors hover:text-[#F7F7F7] disabled:cursor-default disabled:opacity-40"
                                    @click="expanded = !expanded">
                                    <IconChevronDown :size="14" class="shrink-0 transition-transform duration-200" :class="expanded ? '' : '-rotate-90'" />
                                    {{ expanded ? 'Hide what gets sent' : 'See exactly what gets sent' }}
                                </button>

                                <div v-if="expanded && submission" class="mt-3 rounded-xl border border-[#22262F] bg-[#0C0E12] p-4">
                                    <SubmissionPreview :document="submission.document" />
                                </div>
                            </div>

                            <p v-if="error" class="mt-4 text-xs leading-relaxed text-[#F97066]">
                                {{ error }} <button type="button" class="cursor-pointer underline underline-offset-2" @click="load">Try again</button>
                            </p>

                            <p v-else-if="submission && !submission.prefill" class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-[#F79009]">
                                <IconShield :size="14" class="mt-px shrink-0" />
                                <span>This result is too large to pre-fill. It's copied to your clipboard &mdash; paste it into the block GitHub opens.</span>
                            </p>

                            <button @click="submit" type="button" :disabled="!ready"
                                class="mt-5 flex w-full cursor-pointer items-center justify-center rounded-lg border border-[#E62E05] bg-[#E62E05] py-3.5 text-base font-medium text-white shadow-sm transition-colors duration-200 hover:border-[#F13D12] hover:bg-[#F13D12] disabled:cursor-wait disabled:border-[#22262F] disabled:bg-[#13161B] disabled:text-[#61656C]">
                                {{ ready ? 'Submit result' : 'Preparing…' }}
                                <IconArrowUpRight v-if="ready" class="ml-2" :size="18" />
                            </button>

                            <p class="mt-3 text-center text-xs leading-relaxed text-[#61656C]">
                                Opens a pre-filled GitHub issue. A bot files the pull request, credited to your account.
                            </p>

                            <p class="mt-5 border-t border-[#22262F] pt-5 text-center text-sm text-[#94979C]">
                                Prefer social? <button @click="$emit('share')" type="button" class="cursor-pointer text-[#CECFD2] underline decoration-[#373A41] underline-offset-4 hover:decoration-[#94979C]">Share on X instead &rarr;</button>
                            </p>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconArrowUpRight from '@/Components/Icons/IconArrowUpRight.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import SubmissionPreview from '@/Components/Submit/SubmissionPreview.vue';
import { copyToken, fetchSubmission, openIssue } from '@/Composables/useSubmitResults';
import { useHostEditor } from '@/Composables/useHostDetails';
import { formatMs, serverLabelFor } from '@/Composables/useRunSummary';

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

const expanded = ref(false);
const loading = ref(false);
const error = ref(null);
const submission = ref(null);

// Host details autosave to the run silently, same as the share flow.
const {
    host,
    history,
    saved: metaSaved,
    pending,
    seed,
    flush,
} = useHostEditor({
    runId: () => props.run.id,
    active: () => props.open,
    onSaved: (meta) => {
        Object.assign(props.run.meta, meta);
        // The stored run just changed, so the token built from it is stale.
        load();
    },
});

/**
 * The submission is built server-side from the stored run, so it's fetched up
 * front and refreshed whenever the host details land. That's what lets this be
 * one screen instead of two: by the time anyone reaches the button, the thing
 * it opens is already prepared.
 */
const load = async () => {
    loading.value = true;
    error.value = null;

    try {
        submission.value = await fetchSubmission(props.run.id);
    } catch (problem) {
        error.value = problem.message;
    } finally {
        loading.value = false;
    }
};

// Disabled while an edit is still in the autosave debounce: a token built a
// keystroke early would publish a half-typed host name.
const ready = computed(() => Boolean(submission.value) && !loading.value && !pending.value && !error.value);

const identity = computed(() => {
    const summary = props.run.summary ?? {};

    return [
        serverLabelFor(props.run) ?? props.run.meta?.label,
        summary.http_rps != null ? `${Math.round(summary.http_rps).toLocaleString()} req/s` : null,
        summary.http_p95_ms != null ? `p95 ${formatMs(summary.http_p95_ms)}` : null,
    ].filter(Boolean).join(' · ');
});

watch(() => props.open, (open) => {
    if( open ) {
        seed(props.run.meta ?? {});
        metaSaved.value = false;
        expanded.value = false;
        submission.value = null;
        error.value = null;
        load();
    }
});

const close = () => emit('close');

// Neither the clipboard write nor the popup may be awaited first — both want a
// live user gesture. Nothing here needs awaiting anyway; that's the point of
// preparing the submission on open.
const submit = () => {
    copyToken(submission.value.token);
    openIssue(submission.value.issue_url);
    close();
};

// A pending edit resolves on its own; catch the case where it settles after a
// failed load so the button doesn't stay stuck on "Preparing…".
watch(pending, (isPending) => {
    if( !isPending && props.open && !submission.value && !loading.value ) {
        load();
    }
});
</script>
