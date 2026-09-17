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
                        <DialogPanel class="w-full max-w-lg rounded-2xl border border-[#22262F] bg-[#0C0E12] p-6 sm:p-7">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">{{ blocked ? 'This run can\'t go in the gallery' : 'Add your result to the gallery' }}</DialogTitle>
                                    <!-- Which run, stated as its numbers: the identity that
                                         matters here isn't the label, it's the result. -->
                                    <p class="mt-1 truncate font-mono text-xs text-[#61656C] tabular-nums">{{ identity }}</p>
                                </div>
                                <button class="-mt-1 -mr-2 shrink-0 cursor-pointer p-2 text-[#61656C] transition-colors hover:text-[#CECFD2]" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose :size="20" />
                                </button>
                            </div>

                            <!-- A run that measured the load generator, or measured errors,
                                 is not a slow result — it is a different measurement, and no
                                 label makes it comparable. There is nothing to decide here, so
                                 the form and the button are gone rather than disabled. -->
                            <template v-if="blocked">
                                <div class="mt-6 flex flex-col gap-5">
                                    <div v-for="caveat in blockers" :key="caveat.key" class="flex gap-3">
                                        <span aria-hidden="true" class="mt-1.5 size-2 shrink-0 rounded-full bg-[#F97066]"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-[#F7F7F7]">{{ caveat.title }}</p>
                                            <p class="mt-1 text-sm leading-relaxed text-[#94979C]">{{ caveat.detail }}</p>
                                            <p v-if="caveat.fix" class="mt-2.5 rounded-lg border border-[#22262F] bg-[#13161B] px-3.5 py-2 text-sm leading-relaxed text-[#CECFD2]">
                                                {{ caveat.fix }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <button @click="close" type="button"
                                    class="mt-6 w-full cursor-pointer rounded-lg border border-[#22262F] bg-[#13161B] py-3 text-sm font-medium text-[#CECFD2] transition-colors duration-200 hover:border-[#373A41] hover:text-[#F7F7F7]">
                                    Close
                                </button>

                                <p class="mt-4 text-center text-xs text-[#61656C]">
                                    A fresh run gets a fresh submission.
                                </p>
                            </template>

                            <template v-else>
                            <!-- Real numbers, but from an application nobody would deploy this
                                 way. Almost nobody means to publish one of these, so it is said
                                 before the form rather than after the button. -->
                            <div v-if="warnings.length" class="mt-6 rounded-xl border border-[#22262F] bg-[#13161B] p-4">
                                <p class="text-sm font-medium text-[#F7F7F7]">This measures a misconfigured app, not this host</p>

                                <ul class="mt-3.5 flex flex-col gap-3">
                                    <li v-for="caveat in warnings" :key="caveat.key" class="flex items-start gap-3">
                                        <span aria-hidden="true" class="mt-[7px] size-1.5 shrink-0 rounded-full bg-[#F79009]"></span>
                                        <div class="min-w-0 grow">
                                            <p class="text-sm text-[#CECFD2]">{{ caveat.title }}</p>
                                            <p v-if="caveat.fix" class="mt-0.5 text-sm leading-relaxed text-[#94979C]">
                                                <span v-for="(part, i) in segments(caveat.fix)" :key="i"
                                                    :class="part.code ? 'font-mono text-[13px] text-[#CECFD2]' : ''">{{ part.value }}</span>
                                            </p>
                                        </div>
                                        <CopyButton v-if="caveat.command" :text="caveat.command" :label="`Copy ${caveat.command}`" />
                                    </li>
                                </ul>
                            </div>

                            <!-- The only thing on this screen that needs a decision. Everything
                                 below it is confirmation, so it gets the top of the modal and
                                 the only visible inputs. -->
                            <div class="mt-6">
                                <div class="flex items-baseline justify-between gap-3">
                                    <h3 class="text-[11px] font-medium tracking-wide text-[#94979C] uppercase">Where did this run? <span class="text-[#61656C]">(optional)</span></h3>
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
                                    <span>Specs, settings, and results are published. Your IP, domain, and logs are stripped out first.</span>
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

                                <div v-if="expanded && submission" class="mt-3">
                                    <SubmissionPreview :document="submission.document" />
                                </div>
                            </div>

                            <!-- A run id is minted once per benchmark, so the bot rejects the
                                 same run twice. Say so here rather than letting them find out
                                 from a closed issue. -->
                            <p v-if="submittedOn" class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-[#F79009]">
                                <IconShield :size="14" class="mt-px shrink-0" />
                                <span>You opened a submission for this run on {{ submittedOn }}. If it was accepted, submitting again will be turned away &mdash; run the benchmark again for a fresh result.</span>
                            </p>

                            <p v-if="error" class="mt-4 text-xs leading-relaxed text-[#F97066]">
                                {{ error }} <button type="button" class="cursor-pointer underline underline-offset-2" @click="load">Try again</button>
                            </p>

                            <p v-else-if="submission && !submission.prefill" class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-[#F79009]">
                                <IconShield :size="14" class="mt-px shrink-0" />
                                <span>This result is too large to pre-fill. It's copied to your clipboard &mdash; paste it into the block GitHub opens.</span>
                            </p>

                            <!-- Still allowed, but it stops looking like the thing to do. -->
                            <button @click="submit" type="button" :disabled="!ready"
                                class="mt-5 flex w-full cursor-pointer items-center justify-center rounded-lg py-3.5 text-base font-medium shadow-sm transition-colors duration-200 disabled:cursor-wait disabled:border-[#22262F] disabled:bg-[#13161B] disabled:text-[#61656C]"
                                :class="warnings.length
                                    ? 'border border-[#22262F] bg-[#13161B] text-[#CECFD2] hover:border-[#373A41] hover:text-[#F7F7F7]'
                                    : 'border border-[#E62E05] bg-[#E62E05] text-white hover:border-[#F13D12] hover:bg-[#F13D12]'">
                                {{ ready ? submitLabel : 'Preparing…' }}
                                <IconArrowUpRight v-if="ready" class="ml-2" :size="18" />
                            </button>

                            <p class="mt-3 text-center text-xs leading-relaxed text-[#61656C]">
                                Opens a pre-filled GitHub issue.
                                <a :href="SUBMIT_DOCS" target="_blank" rel="noopener" class="underline decoration-[#373A41] underline-offset-2 hover:text-[#CECFD2]">How submitting works</a>
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
import { computed, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconArrowUpRight from '@/Components/Icons/IconArrowUpRight.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import SubmissionPreview from '@/Components/Submit/SubmissionPreview.vue';
import { copyToken, fetchSubmission, markSubmitted, openIssue } from '@/Composables/useSubmitResults';
import { useHostEditor } from '@/Composables/useHostDetails';
import { formatMs, runDisplay, serverLabelFor } from '@/Composables/useRunSummary';
import { runCaveats, submissionGate } from '@/Composables/useRunCaveats';
import CopyButton from '@/Components/CopyButton.vue';

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

const emit = defineEmits(['close']);

const SUBMIT_DOCS = 'https://serversideup.net/open-source/benchkit/docs/community-results';

/**
 * The gallery's side of the results page. It gates on the same caveat list the
 * run renders, so nothing is a surprise here that was not already on screen.
 */
const gate = computed(() => submissionGate(runCaveats({
    environment: props.run?.environment,
    http: runDisplay(props.run).http,
})));

const blockers = computed(() => gate.value.blockers);
const warnings = computed(() => gate.value.warnings);
const blocked = computed(() => blockers.value.length > 0);

/** Backtick-delimited spans in a fix render in the mono voice. */
const segments = (text) => String(text)
    .split('`')
    .map((value, index) => ({ value, code: index % 2 === 1 }));

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

const submittedOn = computed(() => {
    const opened = props.run.submission_opened_at;

    return opened
        ? new Date(opened).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : null;
});

const submitLabel = computed(() => {
    if (submittedOn.value) {
        return 'Submit again anyway';
    }

    return warnings.value.length ? 'Submit anyway' : 'Submit result';
});

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

        if (!blocked.value) {
            load();
        }
    }
});

const close = () => emit('close');

// Neither the clipboard write nor the popup may be awaited first — both want a
// live user gesture. Nothing here needs awaiting anyway; that's the point of
// preparing the submission on open.
const submit = () => {
    copyToken(submission.value.token);
    openIssue(submission.value.issue_url);
    markSubmitted(props.run.id);
    props.run.submission_opened_at ??= new Date().toISOString();
    close();
};

// A pending edit resolves on its own; catch the case where it settles after a
// failed load so the button doesn't stay stuck on "Preparing…".
watch(pending, (isPending) => {
    if( !isPending && props.open && !blocked.value && !submission.value && !loading.value ) {
        load();
    }
});
</script>
