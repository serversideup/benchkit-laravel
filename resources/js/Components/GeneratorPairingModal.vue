<template>
    <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-[99999]" @close="cancel">
            <TransitionChild
                as="template"
                enter="ease-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-200"
                leave-from="opacity-100"
                leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/70" aria-hidden="true" />
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
                        <DialogPanel class="w-full max-w-xl rounded-xl border border-[#22262F] bg-[#0C0E12] p-5 sm:p-8">
                            <DialogTitle class="text-lg text-[#F7F7F7] font-mono">Connect a load generator</DialogTitle>

                            <p class="mt-2 text-sm text-[#94979C] font-mono leading-relaxed">
                                Run this on a machine near this server &mdash; same datacenter or region.
                                A distant machine measures the network between the two, not this server.
                                The benchmark starts the moment it connects.
                            </p>

                            <div class="mt-5 flex items-center justify-between gap-2 rounded-md bg-black px-3 py-2.5">
                                <code class="flex-1 text-xs sm:text-[13px] font-mono break-all">
                                    <span class="text-[#61656C] select-none">$ </span><span class="text-[#CECFD2]">{{ command ?? '…' }}</span>
                                </code>
                                <CopyButton v-if="command" :text="command" label="Copy the generator command" />
                            </div>

                            <div class="mt-4 min-h-10">
                                <template v-if="connected">
                                    <p class="inline-flex items-center gap-2 text-sm font-mono text-[#47CD89]">
                                        <svg viewBox="0 0 20 20" fill="none" class="size-4" aria-hidden="true">
                                            <path d="M16.7 5.3 8.4 13.6 4.3 9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Generator connected
                                    </p>
                                    <p class="mt-1 text-xs font-mono text-[#94979C]">
                                        {{ connectedLine }}<span v-if="ceiling"> &middot; can measure up to ~{{ ceiling.toLocaleString() }} req/s</span>
                                    </p>
                                    <p class="mt-1 text-xs font-mono text-[#61656C]">Starting the benchmark&hellip;</p>
                                </template>
                                <template v-else>
                                    <p class="inline-flex items-center gap-2 text-sm font-mono text-[#94979C]">
                                        <span class="size-1.5 rounded-full bg-[#F79009] animate-pulse"></span>
                                        Waiting for it to connect&hellip;
                                    </p>
                                    <p class="mt-1 text-xs font-mono text-[#61656C]">
                                        Needs a shell with curl. It installs nothing and exits when the test is done.
                                    </p>
                                </template>
                            </div>

                            <div class="mt-6 flex items-center justify-between border-t border-[#22262F] pt-4">
                                <button @click="useSelfTest" :disabled="connected"
                                    class="text-xs font-mono text-[#61656C] hover:text-[#94979C] underline underline-offset-4 decoration-[#373A41] cursor-pointer transition-colors duration-200 disabled:opacity-50">
                                    Use a self-test for this run
                                </button>
                                <button @click="cancel" :disabled="connected"
                                    class="px-4 py-2 rounded-lg border border-[#373A41] text-sm font-mono text-[#CECFD2] hover:bg-[#22262F] cursor-pointer transition-colors duration-200 disabled:opacity-50">
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
import { computed, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import CopyButton from '@/Components/CopyButton.vue';
import { useGeneratorPairing } from '@/Composables/useGeneratorPairing';
import { useSettings } from '@/Composables/useSettings';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

// `start` means "begin the run now" — emitted once the generator is
// connected, or immediately when the person opts back into a self-test.
const emit = defineEmits(['close', 'start']);

const { form } = useSettings();
const { generator, ensureSession, command, connected, ceiling, boostPolling } = useGeneratorPairing();

const connectedLine = computed(() => {
    const handshake = generator.value?.handshake;

    if (!handshake) {
        return '';
    }

    return [
        handshake.host,
        handshake.oha_version ? `oha ${handshake.oha_version}` : null,
        handshake.rtt_ms != null ? `RTT ${handshake.rtt_ms}ms` : null,
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
