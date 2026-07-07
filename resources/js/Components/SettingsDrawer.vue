<template>
    <TransitionRoot 
        as="template" 
        :show="open" 
        enter="transition ease-in-out duration-500" 
        enter-from="-translate-x-full" 
        enter-to="translate-x-0" 
        leave="transition ease-in-out duration-500" 
        leave-from="translate-x-0" 
        leave-to="-translate-x-full">
            <div class="fixed left-0 top-0 bottom-0 bg-black z-[99999] w-[400px] p-6 flex flex-col">
                <div class="flex flex-shrink-0 items-start justify-between">
                    <div class="flex items-start">
                        <img src="/images/icons/square-settings.svg" alt="Settings" class="w-10 h-10" />
                        <div class="flex flex-col ml-4">
                            <h2 class="text-lg text-[#F7F7F7] font-mono">Settings</h2>
                            <p class="text-sm text-[#94979C] font-mono">Choose test behavior.</p>
                        </div>
                    </div>
                    <button class="p-2 cursor-pointer -mt-2 -mr-2" @click="cancel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M15 5L5 15M5 5L15 15" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>

                <div class="flex-shrink-0 flex items-center justify-between mt-6">
                    <div class="inline-flex rounded-lg border border-[#373A41] bg-[#0C0E12] p-1 font-mono text-xs">
                        <button @click="fillPreset('quick')" class="px-3 py-1.5 rounded-md cursor-pointer transition-colors duration-200"
                            :class="activePreset === 'quick' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">Quick</button>
                        <button @click="fillPreset('full')" class="px-3 py-1.5 rounded-md cursor-pointer transition-colors duration-200"
                            :class="activePreset === 'full' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">Full</button>
                    </div>
                    <button v-show="form.isDirty" @click="form.reset()" class="text-xs font-mono text-[#94979C] hover:text-[#CECFD2] underline cursor-pointer">Reset changes</button>
                </div>

                <div class="flex flex-col flex-1 overflow-y-auto">
                    <div class="flex flex-col pb-6 border-b border-[#22262F]">
                        <div class="flex flex-col mt-6">
                            <div class="flex items-center">
                                <Switch
                                    v-model="form.hardware"
                                    :class="form.hardware ? 'bg-[#E62E05]' : 'bg-transparent border border-[#373A41]'"
                                    class="relative inline-flex h-[22px] w-9 shrink-0 cursor-pointer rounded-full border-2 border-[#373A41] transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75">
                                    <span class="sr-only">Enable Hardware Test</span>
                                    <span
                                        aria-hidden="true"
                                        :class="form.hardware ? 'translate-x-[15px]' : 'translate-x-0'"
                                        class="pointer-events-none inline-block mt-px h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    />
                                </Switch>
                                <label class="ml-2.5 text-sm text-[#F7F7F7] font-mono">Hardware Test</label>  
                            </div>
                            <p class="mt-2 text-sm text-[#94979C] font-mono">
                                Use <a href="https://github.com/masonr/yet-another-bench-script" target="_blank" class="underline font-mono">yet-another-benchmark-script</a> to perform hardware tests with fio, Geekbench, and iperf.
                            </p>
                        </div>

                        <div class="flex items-center mt-2" v-show="form.hardware">
                            <input type="checkbox" id="disk" v-model="form.disk" class="w-5 h-5 appearance-none border border-[#373A41] bg-transparent checked:bg-[#E62E05] checked:text-white rounded-md ring-0 outline-none focus:ring-0 focus:ring-offset-0" />
                            <label for="disk" class="ml-3 font-medium text-[#CECFD2] font-mono">Disk test (fio)</label>
                        </div>

                        <div class="flex items-start mt-1" v-show="form.hardware">
                            <input type="checkbox" id="geekbench" v-model="form.geekbench" class="w-5 h-5 appearance-none border border-[#373A41] bg-transparent checked:bg-[#E62E05] checked:text-white rounded-md ring-0 outline-none focus:ring-0 focus:ring-offset-0" />
                            <div class="flex flex-col flex-1 ml-3">
                                <label for="geekbench" class="font-medium text-[#CECFD2] font-mono">Geekbench test</label>
                                
                                <div class="flex flex-col mt-2.5" v-show="form.geekbench">
                                    <label for="geekbench-version" class="text-sm text-[#CECFD2] font-mono font-medium">Geekbench version <span class="text-[#94979C] font-mono font-medium">*</span></label>
                                    <select id="geekbench-version" v-model="form.geekbench_version" class="mt-1.5 w-full px-3 py-2 rounded-lg border border-[#373A41] bg-transparent text-sm text-[#CECFD2] font-mono focus:outline-none focus:ring-0 focus:ring-offset-0">
                                        <option value="">Select version</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center mt-2" v-show="form.hardware">
                            <input type="checkbox" id="iperf" v-model="form.iperf" class="w-5 h-5 appearance-none border border-[#373A41] bg-transparent checked:bg-[#E62E05] checked:text-white rounded-md ring-0 outline-none focus:ring-0 focus:ring-offset-0" />
                            <label for="iperf" class="ml-3 font-medium text-[#CECFD2] font-mono">Iperf test</label>
                        </div>
                    </div>

                    <div class="flex flex-col py-6 border-b border-[#22262F]">
                        <div class="flex flex-col">
                            <div class="flex items-center">
                                <Switch
                                    v-model="form.network"
                                    :class="form.network ? 'bg-[#E62E05]' : 'bg-transparent border border-[#373A41]'"
                                    class="relative inline-flex h-[22px] w-9 shrink-0 cursor-pointer rounded-full border-2 border-[#373A41] transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75">
                                    <span class="sr-only">Enable Network Test</span>
                                    <span
                                        aria-hidden="true"
                                        :class="form.network ? 'translate-x-[15px]' : 'translate-x-0'"
                                        class="pointer-events-none inline-block mt-px h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    />
                                </Switch>
                                <label class="ml-2.5 text-sm text-[#F7F7F7] font-mono">Network Test</label>  
                            </div>
                            <p class="mt-2 text-sm text-[#94979C] font-mono">
                                Use <a href="https://github.com/code-inflation/cfspeedtest" target="_blank" class="underline font-mono">cfspeedtest</a> to perform a network test against CloudFlare's network.
                            </p>
                        </div>

                        <div class="flex flex-col mt-4" v-show="form.network">
                            <label for="network-test-type" class="text-[#CECFD2] font-mono font-medium">Test type <span class="text-[#94979C] font-mono font-medium">*</span></label>
                            <select id="network-test-type" v-model="form.network_test_type" class="mt-1.5 w-full px-3 py-2 rounded-lg border border-[#373A41] bg-transparent text-sm text-[#CECFD2] font-mono focus:outline-none focus:ring-0 focus:ring-offset-0">
                                <option value="">Select type</option>
                                <option value="ipv4">IPv4</option>
                                <option value="ipv6">IPv6</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col py-6 border-b border-[#22262F]">
                        <div class="flex flex-col">
                            <div class="flex items-center">
                                <Switch
                                    v-model="form.http"
                                    :class="form.http ? 'bg-[#E62E05]' : 'bg-transparent border border-[#373A41]'"
                                    class="relative inline-flex h-[22px] w-9 shrink-0 cursor-pointer rounded-full border-2 border-[#373A41] transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75">
                                    <span class="sr-only">Enable Web Server Test</span>
                                    <span
                                        aria-hidden="true"
                                        :class="form.http ? 'translate-x-[15px]' : 'translate-x-0'"
                                        class="pointer-events-none inline-block mt-px h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    />
                                </Switch>
                                <label class="ml-2.5 text-sm text-[#F7F7F7] font-mono">Web Server Test</label>
                            </div>
                            <p class="mt-2 text-sm text-[#94979C] font-mono">
                                Use <a href="https://github.com/hatoo/oha" target="_blank" class="underline font-mono">oha</a> to load test this app's web server against itself (self-test) and measure requests per second.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col py-6 border-b border-[#22262F]">
                        <div class="flex flex-col">
                            <div class="flex items-center">
                                <Switch
                                    v-model="form.php_database"
                                    :class="form.php_database ? 'bg-[#E62E05]' : 'bg-transparent border border-[#373A41]'"
                                    class="relative inline-flex h-[22px] w-9 shrink-0 cursor-pointer rounded-full border-2 border-[#373A41] transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75">
                                    <span class="sr-only">Enable PHP Database Test</span>
                                    <span
                                        aria-hidden="true"
                                        :class="form.php_database ? 'translate-x-[15px]' : 'translate-x-0'"
                                        class="pointer-events-none inline-block mt-px h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    />
                                </Switch>
                                <label class="ml-2.5 text-sm text-[#F7F7F7] font-mono">PHP Database Test</label>  
                            </div>
                            <p class="mt-2 text-sm text-[#94979C] font-mono">
                                Perform a series of "CRUD" tests against a database.
                            </p>
                        </div>

                        <div class="flex flex-col mt-4" v-show="form.php_database">
                            <label for="php-mode" class="text-[#CECFD2] font-mono font-medium">Test scope <span class="text-[#94979C] font-mono font-medium">*</span></label>
                            <select id="php-mode" v-model="form.php_mode" class="mt-1.5 w-full px-3 py-2 rounded-lg border border-[#373A41] bg-transparent text-sm text-[#CECFD2] font-mono focus:outline-none focus:ring-0 focus:ring-offset-0">
                                <option value="quick">Quick — headline CRUD only (~1 min)</option>
                                <option value="full">Full suite (~30 min)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0 flex items-center justify-end border-t border-[#22262F] py-4 px-6">
                    <button @click="cancel()" class="px-4 py-2.5 rounded-lg border border-[#373A41] bg-transparent hover:bg-[rgba(255,255,255,0.12)] cursor-pointer transition-colors duration-200 ease-in-out text-sm text-[#CECFD2] font-mono focus:outline-none focus:ring-0 focus:ring-offset-0 mr-3">Cancel</button>
                    <button @click="save()" :disabled="!form.isDirty" class="px-4 py-2.5 rounded-lg border border-[rgba(255,255,255,0.12)] bg-[#E62E05] transition-colors duration-200 ease-in-out text-sm text-white font-mono focus:outline-none focus:ring-0 focus:ring-offset-0"
                        :class="form.isDirty ? 'hover:bg-[#E62E05]/80 cursor-pointer' : 'opacity-50 cursor-not-allowed'">Save</button>
                </div>
            </div>
    </TransitionRoot>
</template>
  
<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { TransitionRoot } from '@headlessui/vue'
import { Switch } from '@headlessui/vue'
import { useSettings } from '@/Composables/useSettings'
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue'

const { form, saveSettings, fillPreset, activePreset } = useSettings()
const {
    previewStatuses
} = useBenchmarkQueue()

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

// Fields are live-bound to the shared form, so closing without saving
// must revert to the last saved values
const cancel = () => {
   form.reset();
   close();
}

const save = () => {
    saveSettings();
    close();
}

const close = () => {
    emit('close');
}

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.open) {
        cancel();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

// Benchmark options are sent as the POST body when each stage starts
// (see useBenchmarkQueue); the drawer only previews pending/skipped states.
watch(form, () => {
    previewStatuses();
}, { deep: true });
</script>