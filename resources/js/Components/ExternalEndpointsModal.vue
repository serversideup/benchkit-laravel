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
                        <DialogPanel class="w-full max-w-2xl rounded-xl border border-[#22262F] bg-[#0C0E12] p-5 sm:p-8">
                            <div class="flex items-start justify-between">
                                <DialogTitle class="text-lg text-[#F7F7F7] font-mono">Test from your own machine</DialogTitle>
                                <button class="p-2 cursor-pointer -mt-2 -mr-2 text-[#61656C]" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose />
                                </button>
                            </div>

                            <p class="mt-2 text-sm text-[#94979C] font-mono leading-relaxed">
                                These endpoints skip session and CSRF middleware, so any machine that can
                                reach this server can load test them. Install
                                <a href="https://github.com/hatoo/oha" target="_blank" class="text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]">oha</a>
                                (<span class="text-[#CECFD2]">brew install oha</span>) and run a command below.
                            </p>

                            <div class="mt-6 rounded-lg border border-[#22262F] divide-y divide-[#22262F]">
                                <div v-for="endpoint in endpoints" :key="endpoint.path" class="p-4 sm:p-5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="shrink-0 rounded border border-[#373A41] px-1.5 py-0.5 text-[10px] leading-none font-mono text-[#94979C]">GET</span>
                                        <span class="text-sm font-mono text-[#F7F7F7]">{{ endpoint.path }}</span>
                                    </div>
                                    <p class="mt-1.5 text-xs text-[#94979C] font-mono">{{ endpoint.description }}</p>

                                    <div class="mt-3 flex items-center justify-between gap-2 rounded-md bg-black px-3 py-2">
                                        <code class="flex-1 text-xs sm:text-[13px] font-mono break-all">
                                            <span class="text-[#61656C] select-none">$ </span><span class="text-[#CECFD2]">{{ command(endpoint) }}</span>
                                        </code>
                                        <CopyButton :text="command(endpoint)" :label="`Copy command for ${endpoint.path}`" />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 rounded-lg border border-[#22262F] bg-[rgba(255,255,255,0.02)] p-4 sm:p-5">
                                <p class="text-sm text-[#F7F7F7] font-mono">Honest tail latency</p>
                                <p class="mt-1.5 text-xs text-[#94979C] font-mono leading-relaxed">
                                    A fixed-connection run reports throughput well but understates p99 (coordinated omission). For real tail latency, drive a constant request rate and let oha correct for it &mdash; run a command above to find the max req/s, then use about 70% of it as the rate:
                                </p>
                                <div class="mt-3 flex items-center justify-between gap-2 rounded-md bg-black px-3 py-2">
                                    <code class="flex-1 text-xs sm:text-[13px] font-mono break-all">
                                        <span class="text-[#61656C] select-none">$ </span><span class="text-[#CECFD2]">{{ latencyCommand }}</span>
                                    </code>
                                    <CopyButton :text="latencyCommand" label="Copy latency command" />
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-[#61656C] font-mono leading-relaxed">
                                External runs include network time and keep the load generator off this machine &mdash;
                                expect different numbers than the built-in Web Server Load Test.
                            </p>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import CopyButton from '@/Components/CopyButton.vue'
import IconClose from '@/Components/Icons/IconClose.vue'

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const endpoints = [
    { path: '/bench/static', description: 'Framework baseline — static response, no database' },
    { path: '/bench/json', description: 'API response — 25-item JSON payload' },
    { path: '/bench/db-read', description: 'Database read — 20 rows queried per request' },
    { path: '/bench/io?ms=100', description: 'Simulated I/O — sleeps ~100ms to mimic one outbound call' },
]

const origin = window.location.origin

// Closed-loop throughput (saturation): the max req/s a fixed connection
// count can drive.
const command = (endpoint) => `oha -z 30s -c 50 ${origin}${endpoint.path}`

// Open-model + coordinated-omission correction for honest tail latency.
// --latency-correction is a no-op without a target rate (-q); pick ~70% of
// the throughput a command above reports.
const latencyCommand = `oha -q <req_per_sec> -z 30s --latency-correction ${origin}/bench/db-read`

const close = () => {
    emit('close');
}
</script>
