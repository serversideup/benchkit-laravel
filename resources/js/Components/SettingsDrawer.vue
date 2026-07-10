<template>
    <TransitionRoot as="template" :show="open">
        <div class="fixed inset-0 z-[99999]">
            <TransitionChild
                as="template"
                enter="transition-opacity ease-in-out duration-500"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="transition-opacity ease-in-out duration-500"
                leave-from="opacity-100"
                leave-to="opacity-0">
                <div class="absolute inset-0 bg-black/40" aria-hidden="true" @click="attemptClose" />
            </TransitionChild>

            <TransitionChild
                as="template"
                enter="transition ease-in-out duration-500"
                enter-from="translate-x-full"
                enter-to="translate-x-0"
                leave="transition ease-in-out duration-500"
                leave-from="translate-x-0"
                leave-to="translate-x-full">
                <div class="absolute right-0 top-0 bottom-0 bg-black w-[400px] max-w-full p-6 flex flex-col">
                <div class="flex flex-shrink-0 items-start justify-between">
                    <div class="flex items-start">
                        <img src="/images/icons/square-settings.svg" alt="Settings" class="w-10 h-10" />
                        <div class="flex flex-col ml-4">
                            <h2 class="text-lg text-[#F7F7F7] font-mono">Settings</h2>
                            <p class="text-sm text-[#94979C] font-mono">Choose test behavior.</p>
                        </div>
                    </div>
                    <button class="p-2 cursor-pointer -mt-2 -mr-2 text-[#61656C]" @click="attemptClose">
                        <span class="sr-only">Close</span>
                        <IconClose />
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
                        <ToggleRow v-model="form.hardware" label="Hardware Test" class="mt-6">
                            Use <a href="https://github.com/masonr/yet-another-bench-script" target="_blank" class="underline font-mono">yet-another-benchmark-script</a> to perform hardware tests with fio, Geekbench, and iperf.
                        </ToggleRow>

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
                        <ToggleRow v-model="form.network" label="Network Test">
                            Use <a href="https://github.com/code-inflation/cfspeedtest" target="_blank" class="underline font-mono">cfspeedtest</a> to perform a network test against CloudFlare's network.
                        </ToggleRow>

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
                        <ToggleRow v-model="form.http" label="Web Server Load Test">
                            Use <a href="https://github.com/hatoo/oha" target="_blank" class="underline font-mono">oha</a> to load test this app's web server against itself (self-test) and measure requests per second.
                        </ToggleRow>

                        <div class="grid grid-cols-2 gap-3 mt-4" v-show="form.http">
                            <div class="flex flex-col">
                                <label for="http-duration" class="text-sm text-[#CECFD2] font-mono font-medium">Duration (seconds)</label>
                                <input type="number" id="http-duration" v-model.number="form.http_duration" min="5" max="60" step="1"
                                    class="mt-1.5 w-full px-3 py-2 rounded-lg border border-[#373A41] bg-transparent text-sm text-[#CECFD2] font-mono focus:outline-none focus:border-[#61656C] focus:ring-0 focus:ring-offset-0" />
                            </div>
                            <div class="flex flex-col">
                                <label for="http-connections" class="text-sm text-[#CECFD2] font-mono font-medium">Connections</label>
                                <input type="number" id="http-connections" v-model.number="form.http_connections" min="1" max="500" step="1"
                                    class="mt-1.5 w-full px-3 py-2 rounded-lg border border-[#373A41] bg-transparent text-sm text-[#CECFD2] font-mono focus:outline-none focus:border-[#61656C] focus:ring-0 focus:ring-offset-0" />
                            </div>
                        </div>

                        <p v-show="form.http && !standardHttpLoad" class="mt-2.5 text-xs text-[#94979C] font-mono">
                            Custom load — results won't be directly comparable with standard BenchKit runs.
                            <button @click="resetHttpLoad()" type="button" class="text-[#CECFD2] underline underline-offset-2 hover:text-white cursor-pointer">Reset to 10s &times; 50</button>
                        </p>
                    </div>

                    <div class="flex flex-col py-6 border-b border-[#22262F]">
                        <ToggleRow v-model="form.php_database" label="PHP Database Test">
                            Perform a series of "CRUD" tests against a database.
                        </ToggleRow>

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
                    <button @click="attemptClose()" class="px-4 py-2.5 rounded-lg border border-[#373A41] bg-transparent hover:bg-[rgba(255,255,255,0.12)] cursor-pointer transition-colors duration-200 ease-in-out text-sm text-[#CECFD2] font-mono focus:outline-none focus:ring-0 focus:ring-offset-0 mr-3">Cancel</button>
                    <button @click="save()" class="px-4 py-2.5 rounded-lg border border-[rgba(255,255,255,0.12)] bg-[#E62E05] hover:bg-[#E62E05]/80 cursor-pointer transition-colors duration-200 ease-in-out text-sm text-white font-mono focus:outline-none focus:ring-0 focus:ring-offset-0">Save</button>
                </div>
                </div>
            </TransitionChild>
        </div>
    </TransitionRoot>

    <ConfirmModal :open="confirmingClose"
        title="Unsaved changes"
        message="You changed some test settings but haven't saved them. Close now and your setup reverts to the last saved settings."
        confirm-label="Discard changes"
        @confirm="discard()" @close="confirmingClose = false" />
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { TransitionChild, TransitionRoot } from '@headlessui/vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import IconClose from '@/Components/Icons/IconClose.vue'
import ToggleRow from '@/Components/ToggleRow.vue'
import { useSettings } from '@/Composables/useSettings'
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue'

const { form, saveSettings, fillPreset, activePreset } = useSettings()
const {
    previewStatuses
} = useBenchmarkQueue()

// The standard BenchKit load — deviating is allowed but always called out,
// since custom loadgen settings make results incomparable with other runs
const standardHttpLoad = computed(() => Number(form.http_duration) === 10 && Number(form.http_connections) === 50)

const resetHttpLoad = () => {
    form.http_duration = 10
    form.http_connections = 50
}

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const confirmingClose = ref(false);

// Fields are live-bound to the shared form, so leaving without saving
// must revert to the last saved values — warn before discarding a draft
const attemptClose = () => {
    if (form.isDirty) {
        confirmingClose.value = true;
        return;
    }

    close();
}

const discard = () => {
    form.reset();
    confirmingClose.value = false;
    close();
}

// Clamp to the backend's validation bounds so a stray or emptied input can
// never fail the stage mid-run; empty falls back to the standard load
const clampHttpLoad = () => {
    const clamp = (value, fallback, min, max) => Math.min(max, Math.max(min, Math.round(Number(value)) || fallback));

    form.http_duration = clamp(form.http_duration, 10, 5, 60);
    form.http_connections = clamp(form.http_connections, 50, 1, 500);
}

const save = () => {
    clampHttpLoad();
    saveSettings();
    close();
}

const close = () => {
    emit('close');
}

watch(() => props.open, () => {
    confirmingClose.value = false;
});

// While the confirm dialog is open it owns Escape (closing itself back
// to "keep editing"), so the drawer must not react to the same keypress
const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.open && !confirmingClose.value) {
        attemptClose();
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
