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
                        <DialogPanel class="w-full max-w-2xl rounded-xl border border-[#22262F] bg-[#0C0E12] p-6 sm:p-8">
                            <div class="flex items-center justify-between">
                                <DialogTitle class="text-lg font-semibold text-[#F7F7F7]">Share on X</DialogTitle>
                                <button class="p-2 cursor-pointer -mr-2" @click="close">
                                    <span class="sr-only">Close</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 20 20" fill="none">
                                        <path d="M15 5L5 15M5 5L15 15" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-5 relative group">
                                <img v-if="imageDataUrl" :src="imageDataUrl" alt="Your results card" class="w-full rounded-lg border border-[#22262F] shadow-2xl" />
                                <div v-else class="w-full aspect-video rounded-lg bg-[#13161B] animate-pulse"></div>

                                <div v-if="imageDataUrl" class="absolute top-3 right-3 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <button @click="copyImageOnly()" type="button" :aria-label="copyState === 'copied' ? 'Copied' : 'Copy image'"
                                        class="p-2 rounded-lg bg-black/75 backdrop-blur border border-white/10 cursor-pointer transition-colors duration-200"
                                        :class="copyState === 'copied' ? 'text-[#47CD89]' : 'text-[#CECFD2] hover:text-white hover:bg-black/90'">
                                        <svg v-if="copyState === 'copied'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 10.5L8.5 15L16 5.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <rect x="7.5" y="7.5" width="8.5" height="8.5" rx="1.5" stroke="currentColor" stroke-width="1.66667"/>
                                            <path d="M12.5 7.5V5.5C12.5 4.67157 11.8284 4 11 4H5.5C4.67157 4 4 4.67157 4 5.5V11C4 11.8284 4.67157 12.5 5.5 12.5H7.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                    <button @click="downloadImage()" type="button" aria-label="Download image"
                                        class="p-2 rounded-lg bg-black/75 backdrop-blur border border-white/10 text-[#CECFD2] hover:text-white hover:bg-black/90 cursor-pointer transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 19 17" fill="none">
                                            <path d="M2.50016 11.8685C1.49517 11.1958 0.833496 10.0502 0.833496 8.75C0.833496 6.79702 2.32642 5.19274 4.23328 5.01614C4.62334 2.64344 6.6837 0.833332 9.16683 0.833332C11.65 0.833332 13.7103 2.64344 14.1004 5.01614C16.0072 5.19274 17.5002 6.79702 17.5002 8.75C17.5002 10.0502 16.8385 11.1958 15.8335 11.8685M5.8335 12.5L9.16683 15.8333M9.16683 15.8333L12.5002 12.5M9.16683 15.8333V8.33333" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button v-if="!detailsOpen" @click="detailsOpen = true" type="button" class="w-full text-center text-sm text-[#94979C] hover:text-[#CECFD2] cursor-pointer transition-colors duration-200">
                                    + Add hosting details &mdash; help the community compare hosts
                                </button>
                                <div v-else class="rounded-xl border border-[#22262F] bg-[#13161B]/40 p-4">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <p class="text-sm font-semibold text-[#F7F7F7]">Hosting details <span class="text-[#61656C] font-normal">&mdash; shown on the card</span></p>
                                        <span class="flex items-baseline gap-4 shrink-0 text-sm">
                                            <span v-if="metaSaved" class="text-xs text-[#47CD89]">Saved</span>
                                            <button v-if="hasAnyValue" @click="confirmingClear = true" type="button" class="text-[#94979C] hover:text-[#F97066] cursor-pointer transition-colors duration-200">Clear</button>
                                        </span>
                                    </div>
                                    <div class="mt-3.5 grid grid-cols-2 gap-3.5">
                                        <div v-for="field in FIELDS" :key="field.key" class="flex flex-col gap-1">
                                            <label :for="`share-${field.key}`" class="text-xs text-[#94979C]">{{ field.label }}</label>
                                            <input :id="`share-${field.key}`" v-model="meta[field.key]" type="text" :maxlength="field.max" :placeholder="field.placeholder" :list="`share-${field.key}-options`"
                                                class="rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 text-sm text-[#F7F7F7] font-mono placeholder:text-[#61656C] focus:outline-none focus:border-[#61656C]">
                                            <datalist :id="`share-${field.key}-options`">
                                                <option v-for="entry in history[field.key]" :key="entry" :value="entry" />
                                            </datalist>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button @click="openX()" type="button" :disabled="!imageDataUrl" class="mt-5 w-full rounded-lg py-3.5 flex items-center justify-center text-base font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                Copy image &amp; open
                                <svg class="ml-2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                    <path d="M10.1909 7.41006L16.5656 0H15.055L9.51988 6.43405L5.09898 0H0L6.68527 9.72942L0 17.5H1.51068L7.35593 10.7054L12.0247 17.5H17.1237L10.1906 7.41006H10.1909ZM8.12184 9.81514L7.44449 8.84631L2.055 1.13722H4.37532L8.7247 7.3587L9.40206 8.32753L15.0557 16.4145H12.7354L8.12184 9.81551V9.81514Z" fill="white"/>
                                </svg>
                            </button>

                            <p class="mt-3 text-sm text-center" :class="copyState === 'failed' ? 'text-[#F97066]' : 'text-[#94979C]'">
                                <template v-if="copyState === 'failed'">
                                    We couldn't copy the image to your clipboard &mdash;
                                    <button @click="downloadImage()" type="button" class="underline underline-offset-4 decoration-[#373A41] hover:text-[#CECFD2] cursor-pointer">download it</button>
                                    and attach it to your post instead.
                                </template>
                                <template v-else>
                                    X doesn't let us attach images automatically &mdash; so we copy the card for you,
                                    and you just paste it ({{ pasteKeys }}) into your post.
                                </template>
                            </p>

                            <p class="mt-6 pt-5 border-t border-[#22262F] text-sm text-[#94979C] text-center">
                                Keep <a href="https://x.com/search?q=%23BenchKit&f=live" target="_blank" class="text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]">#BenchKit</a> in your post
                                &middot; <a href="https://x.com/search?q=%23BenchKit&f=live" target="_blank" class="hover:text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]">see what others are getting &rarr;</a>
                            </p>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>

    <ConfirmModal :open="confirmingClear"
        title="Clear hosting details?"
        message="This removes the host, plan, datacenter, and cost from this run — and from the prefill for your next one. Your autocomplete suggestions are kept."
        confirm-label="Clear details"
        @confirm="clearHost()" @close="confirmingClear = false" />

    <!-- Off-viewport render target for html-to-image: display:none would
         produce a blank PNG, so it is positioned off-screen instead -->
    <div v-if="open" style="position: fixed; left: -10000px; top: 0;" aria-hidden="true">
        <div ref="renderTarget">
            <TemplateComparison v-if="isComparison" :run="runWithMeta" :run-b="runB" />
            <TemplateFullSuite v-else :run="runWithMeta" />
        </div>
    </div>
</template>

<script setup>
import * as htmlToImage from 'html-to-image';
import { computed, reactive, ref, nextTick, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TemplateFullSuite from '@/Components/Share/templates/TemplateFullSuite.vue';
import TemplateComparison from '@/Components/Share/templates/TemplateComparison.vue';
import { buildRunShareText, buildComparisonShareText } from '@/share/shareText';
import { useShareResults } from '@/Composables/useShareResults';
import { updateRunMeta } from '@/Composables/useRunActions';
import { saveHostDetails, loadHostHistory } from '@/Composables/useHostDetails';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    run: {
        type: Object,
        required: true,
    },
    runB: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

// One card, two modes: sharing from a comparison renders the versus card
const isComparison = computed(() => props.runB != null
    && props.run.stages_completed.some((stage) => props.runB.stages_completed.includes(stage)));

// ----- optional hosting details (live preview, silent autosave) -----
const FIELDS = [
    { key: 'provider', label: 'Host', placeholder: 'DigitalOcean', max: 120 },
    { key: 'plan', label: 'Plan', placeholder: 'Premium AMD 2GB', max: 120 },
    { key: 'datacenter', label: 'Datacenter', placeholder: 'NYC3', max: 120 },
    { key: 'cost', label: 'Monthly cost', placeholder: '$24/mo', max: 60 },
];

const meta = reactive({ provider: '', plan: '', datacenter: '', cost: '' });
const detailsOpen = ref(false);
const metaSaved = ref(false);
const history = loadHostHistory();

const hasAnyValue = computed(() => Boolean(meta.provider || meta.plan || meta.datacenter || meta.cost));
const confirmingClear = ref(false);

const clearHost = () => {
    meta.provider = '';
    meta.plan = '';
    meta.datacenter = '';
    meta.cost = '';
    confirmingClear.value = false;
};

const runWithMeta = computed(() => ({
    ...props.run,
    meta: {
        ...props.run.meta,
        provider: meta.provider || null,
        plan: meta.plan || null,
        datacenter: meta.datacenter || null,
        cost: meta.cost || null,
    },
}));

const shareText = computed(() => isComparison.value
    ? buildComparisonShareText(runWithMeta.value, props.runB)
    : buildRunShareText(runWithMeta.value));

// ----- image generation (1600×900 via pixelRatio on the 1200×675 DOM) -----
const imageDataUrl = ref(null);
const renderTarget = ref(null);

// html-to-image snapshots the DOM as-is: if the brand PNGs haven't finished
// loading yet (first open, cold cache), they rasterize as empty space
const waitForImages = (node) => Promise.all(
    Array.from(node.querySelectorAll('img')).map((img) =>
        img.complete && img.naturalWidth > 0
            ? Promise.resolve()
            : new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            }),
    ),
);

const generate = async () => {
    imageDataUrl.value = null;

    await nextTick();

    if( !renderTarget.value?.firstElementChild ) {
        return;
    }

    await waitForImages(renderTarget.value.firstElementChild);

    try {
        imageDataUrl.value = await htmlToImage.toPng(renderTarget.value.firstElementChild, {
            pixelRatio: 1600 / 1200,
            quality: 1,
            skipFonts: true,
            style: {
                fontFamily: 'JetBrains Mono, monospace',
            },
        });
    } catch (error) {
        console.error(error);
    }
};

watch(() => props.open, (open) => {
    if( open ) {
        meta.provider = props.run.meta?.provider ?? '';
        meta.plan = props.run.meta?.plan ?? props.run.meta?.plan_notes ?? '';
        meta.datacenter = props.run.meta?.datacenter ?? '';
        meta.cost = props.run.meta?.cost ?? '';
        detailsOpen.value = Boolean(meta.provider || meta.plan || meta.datacenter || meta.cost);
        metaSaved.value = false;

        generate();
    }
});

// Typing pauses → card regenerates with the details and they persist to the
// run silently; no save button to think about
let metaTimer = null;

watch(() => `${meta.provider}|${meta.plan}|${meta.datacenter}|${meta.cost}`, (value, previous) => {
    if( !props.open || previous === undefined ) {
        return;
    }

    clearTimeout(metaTimer);
    metaTimer = setTimeout(async () => {
        generate();

        try {
            await updateRunMeta(props.run.id, {
                provider: meta.provider || null,
                plan: meta.plan || null,
                datacenter: meta.datacenter || null,
                cost: meta.cost || null,
            });

            Object.assign(props.run.meta, runWithMeta.value.meta);
            saveHostDetails(meta);
            metaSaved.value = true;
            setTimeout(() => metaSaved.value = false, 2000);
        } catch (error) {
            console.error(error);
        }
    }, 600);
});

// ----- share actions -----
const {
    copyState,
    intentUrl,
    recopyImage,
} = useShareResults({ image: imageDataUrl, shareText });

const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const pasteKeys = isMac ? '⌘V' : 'Ctrl+V';

const copyImageOnly = () => recopyImage();

// The copy happens synchronously in this click gesture (Safari requires it),
// and at the last possible moment so nothing else can replace the clipboard
const openX = () => {
    recopyImage();
    window.open(intentUrl.value, '_blank');
};

const downloadImage = () => {
    const a = document.createElement('a');
    a.href = imageDataUrl.value;
    a.download = isComparison.value ? 'benchkit-comparison.png' : 'benchkit-results.png';
    a.click();
};
</script>
