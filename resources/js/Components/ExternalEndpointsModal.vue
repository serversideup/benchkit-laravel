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
                                <button class="p-2 cursor-pointer -mt-2 -mr-2" @click="close">
                                    <span class="sr-only">Close</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M15 5L5 15M5 5L15 15" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
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
                                        <button @click="copyCommand(endpoint)" class="shrink-0 p-1.5 rounded-md cursor-pointer transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
                                            :class="copiedPath === endpoint.path ? 'text-[#CECFD2]' : 'text-[#61656C] hover:text-[#CECFD2] hover:bg-[rgba(255,255,255,0.06)]'">
                                            <span class="sr-only">{{ copiedPath === endpoint.path ? 'Copied' : `Copy command for ${endpoint.path}` }}</span>
                                            <svg v-if="copiedPath === endpoint.path" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                                <path d="M4 10.5L8.5 15L16 5.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                                <rect x="7.5" y="7.5" width="8.5" height="8.5" rx="1.5" stroke="currentColor" stroke-width="1.66667"/>
                                                <path d="M12.5 7.5V5.5C12.5 4.67157 11.8284 4 11 4H5.5C4.67157 4 4 4.67157 4 5.5V11C4 11.8284 4.67157 12.5 5.5 12.5H7.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-[#61656C] font-mono leading-relaxed">
                                External runs include network time and keep the load generator off this machine &mdash;
                                expect different numbers than the built-in Web Server Test.
                            </p>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'

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
]

const copiedPath = ref(null)
const origin = window.location.origin

const command = (endpoint) => `oha -z 10s -c 50 ${origin}${endpoint.path}`

const close = () => {
    emit('close');
}

// The clipboard API only exists in secure contexts (https or localhost) —
// plain-http homelab setups fall back to a hidden textarea + execCommand
const writeToClipboard = (text) => {
    if (navigator.clipboard) {
        return navigator.clipboard.writeText(text)
    }

    return new Promise((resolve, reject) => {
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        const succeeded = document.execCommand('copy')
        document.body.removeChild(textarea)
        succeeded ? resolve() : reject(new Error('Clipboard unavailable'))
    })
}

const copyCommand = async (endpoint) => {
    try {
        await writeToClipboard(command(endpoint))
        copiedPath.value = endpoint.path
        setTimeout(() => {
            if (copiedPath.value === endpoint.path) {
                copiedPath.value = null
            }
        }, 2000)
    } catch (error) {
        console.error(error)
    }
}
</script>
