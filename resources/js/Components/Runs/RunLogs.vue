<template>
    <section v-if="stages.length" class="py-9">
        <h2 class="text-base font-semibold text-[#F7F7F7]">Console output</h2>

        <div class="mt-6 flex flex-col gap-3">
            <div v-for="stage in stages" :key="stage.key" class="rounded-lg border border-[#22262F] overflow-hidden">
                <button @click="toggle(stage.key)" type="button" class="w-full flex items-center justify-between px-4 py-3 bg-[#13161B] cursor-pointer group">
                    <span class="flex items-center gap-2.5">
                        <Status status="completed" />
                        <span class="text-sm font-medium text-[#F7F7F7]">{{ stage.label }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span @click.stop="copyLog(stage)" role="button" class="p-1.5 rounded-md transition-colors duration-200"
                            :class="copiedStage === stage.key ? 'text-[#47CD89]' : 'text-[#61656C] hover:text-[#CECFD2] hover:bg-[rgba(255,255,255,0.06)]'"
                            :title="copiedStage === stage.key ? 'Copied' : `Copy ${stage.label} log`">
                            <span class="sr-only">{{ copiedStage === stage.key ? 'Copied' : `Copy ${stage.label} log` }}</span>
                            <svg v-if="copiedStage === stage.key" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                <path d="M4 10.5L8.5 15L16 5.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                <rect x="7.5" y="7.5" width="8.5" height="8.5" rx="1.5" stroke="currentColor" stroke-width="1.66667"/>
                                <path d="M12.5 7.5V5.5C12.5 4.67157 11.8284 4 11 4H5.5C4.67157 4 4 4.67157 4 5.5V11C4 11.8284 4.67157 12.5 5.5 12.5H7.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <svg :class="{ 'rotate-180': open === stage.key }" class="transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="none">
                            <path d="M5 7.5L10 12.5L15 7.5" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </button>
                <pre v-show="open === stage.key" class="px-4 py-3 bg-black text-sm text-[#CECFD2] font-mono leading-relaxed overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap">{{ stage.lines.join('\n') }}</pre>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import Status from '@/Pages/Partials/Status.vue';
import { STAGE_LABELS } from '@/Composables/useRunComparison';
import { writeTextToClipboard } from '@/Composables/useClipboard';

const props = defineProps({
    logs: {
        type: Object,
        default: () => ({}),
    },
});

const open = ref(null);
const copiedStage = ref(null);

const toggle = (key) => {
    open.value = open.value === key ? null : key;
};

const copyLog = async (stage) => {
    try {
        await writeTextToClipboard(stage.lines.join('\n'));
        copiedStage.value = stage.key;
        setTimeout(() => {
            if( copiedStage.value === stage.key ) {
                copiedStage.value = null;
            }
        }, 2000);
    } catch (error) {
        console.error(error);
    }
};

const stages = computed(() => Object.entries(props.logs ?? {})
    .filter(([, lines]) => Array.isArray(lines) && lines.length)
    .map(([key, lines]) => ({ key, label: STAGE_LABELS[key] ?? key, lines })));
</script>
