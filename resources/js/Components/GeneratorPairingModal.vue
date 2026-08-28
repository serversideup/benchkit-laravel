<template>
    <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-[99999]" :initial-focus="focusAnchor" @close="cancel">
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
                        <DialogPanel class="w-full max-w-xl rounded-2xl border border-[#22262F] bg-[#0C0E12] p-6 shadow-2xl shadow-black/50 sm:p-8">
                            <!-- Opening the dialog parks focus here rather than on the copy
                                 button, which would arrive wearing its focus ring. -->
                            <span ref="focusAnchor" tabindex="-1" class="sr-only outline-none"></span>

                            <DialogTitle class="text-xl font-semibold tracking-tight text-[#F7F7F7]">
                                Run the load test from another machine
                            </DialogTitle>

                            <p class="mt-2.5 max-w-[54ch] text-sm leading-relaxed text-[#94979C]">
                                For the most accurate results, run the load test from another machine.
                            </p>

                            <!-- Above the command, never below it: nobody should learn what
                                 this needs after they have already pasted a pipe into sh. -->
                            <p class="mt-5 max-w-[54ch] text-xs leading-relaxed text-[#61656C]">
                                That machine needs <span class="font-mono text-[#94979C]">oha</span> installed first.
                                Follow our  
                                <a :href="GENERATOR_DOCS" target="_blank" rel="noopener"
                                    class="text-[#94979C] underline decoration-[#373A41] underline-offset-4 transition-colors duration-200 hover:text-[#CECFD2] hover:decoration-[#61656C]">
                                    Testing from another machine guide
                                </a>
                                on how to get the most accurate results.
                            </p>

                            <!-- The one thing to act on, so it carries the most weight on the
                                 screen: full-width, inset, and the only monospace here. The
                                 copy button sits in a reserved gutter so every wrapped line
                                 ends on the same edge. -->
                            <div class="relative mt-2.5 rounded-xl border border-[#22262F] bg-black px-4 py-3.5 transition-colors duration-200 hover:border-[#373A41]">
                                <code v-if="command" class="block pr-8 font-mono text-[13px] leading-relaxed break-all text-[#CECFD2]">
                                    <span class="text-[#61656C] select-none">$ </span>{{ command }}
                                </code>
                                <div v-else class="py-1.5 pr-8" aria-hidden="true">
                                    <div class="h-3 w-2/3 animate-pulse rounded bg-[#22262F]"></div>
                                </div>
                                <CopyButton v-if="command" :text="command" label="Copy the command" class="absolute top-2.5 right-2.5" />
                            </div>

                            <p class="mt-2.5 max-w-[54ch] text-xs leading-relaxed text-[#61656C]">
                                Pick a machine close to this server. From far away you'd be measuring the
                                network in between, not the server.
                            </p>

                            <div class="mt-5 min-h-11" aria-live="polite">
                                <Transition
                                    mode="out-in"
                                    enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
                                    leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
                                    <div v-if="connected" key="connected">
                                        <p class="flex items-center gap-2 text-sm font-medium text-[#F7F7F7]">
                                            <IconCheck class="shrink-0 text-[#47CD89]" />
                                            Connected. Starting the benchmark&hellip;
                                        </p>
                                        <p v-if="connectedLine" class="mt-1.5 pl-6 font-mono text-xs text-[#61656C] tabular-nums">
                                            {{ connectedLine }}
                                        </p>
                                    </div>

                                    <div v-else key="waiting">
                                        <p class="flex items-center gap-2.5 text-sm text-[#CECFD2]">
                                            <span class="relative flex size-2 shrink-0">
                                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-[#E62E05] opacity-60"></span>
                                                <span class="relative inline-flex size-2 rounded-full bg-[#E62E05]"></span>
                                            </span>
                                            Waiting for that machine to connect
                                        </p>
                                        <p class="mt-1.5 pl-[18px] text-xs leading-relaxed text-[#61656C]">
                                            The run starts as soon as it does.
                                        </p>
                                    </div>
                                </Transition>
                            </div>

                            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[#22262F] pt-5">
                                <p class="text-sm text-[#61656C]">
                                    No second machine?
                                    <button @click="useSelfTest" :disabled="connected"
                                        class="cursor-pointer text-[#CECFD2] underline decoration-[#373A41] underline-offset-4 transition-colors duration-200 hover:text-[#F7F7F7] hover:decoration-[#94979C] disabled:opacity-50">
                                        Test from this server instead
                                    </button>
                                </p>
                                <button @click="cancel" :disabled="connected"
                                    class="cursor-pointer rounded-lg border border-[#373A41] px-4 py-2.5 text-sm font-medium text-[#CECFD2] transition-colors duration-200 hover:border-[#61656C] hover:bg-[#13161B] disabled:opacity-50">
                                    Cancel
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
import { computed, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import CopyButton from '@/Components/CopyButton.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import { useGeneratorPairing } from '@/Composables/useGeneratorPairing';
import { useSettings } from '@/Composables/useSettings';

const GENERATOR_DOCS = 'https://serversideup.net/open-source/benchkit/docs/benchmarks/testing-from-another-machine';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

// `start` means "begin the run now" — emitted once the generator is
// connected, or immediately when the person opts back into a self-test.
const emit = defineEmits(['close', 'start']);

const focusAnchor = ref(null);

const { form } = useSettings();
const { generator, ensureSession, command, connected, ceiling, boostPolling } = useGeneratorPairing();

/**
 * What connected — machine facts, in the monospace voice the results use.
 * It is on screen for about a second, so it stays to one line.
 */
const connectedLine = computed(() => {
    const handshake = generator.value?.handshake;

    if (!handshake) {
        return '';
    }

    return [
        handshake.host,
        handshake.oha_version ? `oha ${handshake.oha_version}` : null,
        handshake.rtt_ms != null ? `${handshake.rtt_ms}ms round trip` : null,
        ceiling.value ? `up to ~${ceiling.value.toLocaleString()} req/s` : null,
    ].filter(Boolean).join(' · ');
});

// While the dialog is up, poll at the live-run cadence so the connection
// shows within a second of the handshake; mint a pairing once the poll has
// confirmed none exists (undefined = not learned yet, null = none).
watch([() => props.open, generator], ([open, session]) => {
    boostPolling.value = open;

    if (open && session === null) {
        ensureSession();
    }
}, { immediate: true });

let started = false;

watch([() => props.open, connected], ([open, isConnected]) => {
    if (!open) {
        started = false;

        return;
    }

    // A beat of visible confirmation before the console takes over —
    // instant dismissal reads as the dialog crashing, not succeeding.
    if (isConnected && !started) {
        started = true;
        setTimeout(() => emit('start'), 900);
    }
}, { immediate: true });

const cancel = () => {
    if (!connected.value) {
        emit('close');
    }
};

/**
 * One run only, deliberately: the start request captures the form
 * synchronously, and the setting reverts so the external default — the
 * accurate mode — greets the next run. A persistent switch lives in the
 * settings drawer.
 */
const useSelfTest = () => {
    form.http_generator = 'self';
    emit('start');
    form.http_generator = 'external';
};
</script>
