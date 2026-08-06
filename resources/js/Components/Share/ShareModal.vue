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
                                <button class="p-2 cursor-pointer -mr-2 text-[#61656C]" @click="close">
                                    <span class="sr-only">Close</span>
                                    <IconClose :size="22" />
                                </button>
                            </div>

                            <div class="mt-5 relative group">
                                <img v-if="imageDataUrl" :src="imageDataUrl" alt="Your results card" class="w-full rounded-lg border border-[#22262F] shadow-2xl" />
                                <div v-else class="w-full aspect-video rounded-lg bg-[#13161B] animate-pulse"></div>

                                <div v-if="imageDataUrl" class="absolute top-3 right-3 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <button @click="recopyImage()" type="button" :aria-label="copyState === 'copied' ? 'Copied' : 'Copy image'"
                                        class="p-2 rounded-lg bg-black/75 backdrop-blur border border-white/10 cursor-pointer transition-colors duration-200"
                                        :class="copyState === 'copied' ? 'text-[#47CD89]' : 'text-[#CECFD2] hover:text-white hover:bg-black/90'">
                                        <IconCheck v-if="copyState === 'copied'" />
                                        <IconClipboard v-else />
                                    </button>
                                    <button @click="downloadImage()" type="button" aria-label="Download image"
                                        class="p-2 rounded-lg bg-black/75 backdrop-blur border border-white/10 text-[#CECFD2] hover:text-white hover:bg-black/90 cursor-pointer transition-colors duration-200">
                                        <IconDownloadCloud :width="16" :height="16" />
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
                                    <HostDetailsFields class="mt-3.5" :host="host" :history="history" id-prefix="share" />
                                </div>
                            </div>

                            <button @click="openX()" type="button" :disabled="!imageDataUrl" class="mt-5 w-full rounded-lg py-3.5 flex items-center justify-center text-base font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                Copy image &amp; open
                                <IconXLogo class="ml-2" :size="18" />
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
        @confirm="confirmClear()" @close="confirmingClear = false" />

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
import { computed, ref, nextTick, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import HostDetailsFields from '@/Components/Runs/HostDetailsFields.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconClipboard from '@/Components/Icons/IconClipboard.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconDownloadCloud from '@/Components/Icons/IconDownloadCloud.vue';
import IconXLogo from '@/Components/Icons/IconXLogo.vue';
import TemplateFullSuite from '@/Components/Share/templates/TemplateFullSuite.vue';
import TemplateComparison from '@/Components/Share/templates/TemplateComparison.vue';
import { buildRunShareText, buildComparisonShareText } from '@/share/shareText';
import { useShareResults } from '@/Composables/useShareResults';
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
// Typing pauses → the card regenerates with the details and they persist to
// the run silently; no save button to think about
const {
    host,
    history,
    saved: metaSaved,
    hasAnyValue,
    clearHost,
    seed,
    costPayload,
} = useHostEditor({
    runId: () => props.run.id,
    active: () => props.open,
    onFlush: () => generate(),
    onSaved: (meta) => Object.assign(props.run.meta, meta),
});

const detailsOpen = ref(false);
const confirmingClear = ref(false);

const confirmClear = () => {
    clearHost();
    confirmingClear.value = false;
};

const runWithMeta = computed(() => ({
    ...props.run,
    meta: {
        ...props.run.meta,
        provider: host.provider || null,
        plan: host.plan || null,
        datacenter: host.datacenter || null,
        cost: costPayload(),
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
        seed(props.run.meta ?? {});
        detailsOpen.value = hasAnyValue.value;
        metaSaved.value = false;

        generate();
    }
});

// ----- share actions -----
const {
    copyState,
    intentUrl,
    recopyImage,
} = useShareResults({ image: imageDataUrl, shareText });

const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const pasteKeys = isMac ? '⌘V' : 'Ctrl+V';

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
