<template>
    <PanelSection v-if="stages.length" title="Console output">
        <div class="mt-6 flex flex-col gap-3">
            <div v-for="stage in stages" :key="stage.key" class="rounded-lg border border-[#22262F] overflow-hidden">
                <button @click="toggle(stage.key)" type="button" class="w-full flex items-center justify-between px-4 py-3 bg-[#13161B] cursor-pointer group">
                    <span class="flex items-center gap-2.5">
                        <Status status="completed" />
                        <span class="text-sm font-medium text-[#F7F7F7]">{{ stage.label }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <CopyButton as="span" :text="stage.lines.join('\n')" :label="`Copy ${stage.label} log`" />
                        <IconChevronDown :class="{ 'rotate-180': open === stage.key }" class="transition-transform duration-200 text-[#61656C]" />
                    </span>
                </button>
                <pre v-show="open === stage.key" class="px-4 py-3 bg-black text-sm text-[#CECFD2] font-mono leading-relaxed overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap">{{ stage.lines.join('\n') }}</pre>
            </div>
        </div>
    </PanelSection>
</template>

<script setup>
import { computed, ref } from 'vue';
import CopyButton from '@/Components/CopyButton.vue';
import PanelSection from '@/Components/PanelSection.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import Status from '@/Pages/Partials/Status.vue';
import { STAGE_HEADINGS } from '@/stages';

const props = defineProps({
    logs: {
        type: Object,
        default: () => ({}),
    },
});

const open = ref(null);

const toggle = (key) => {
    open.value = open.value === key ? null : key;
};

const stages = computed(() => Object.entries(props.logs ?? {})
    .filter(([, lines]) => Array.isArray(lines) && lines.length)
    .map(([key, lines]) => ({ key, label: STAGE_HEADINGS[key] ?? key, lines })));
</script>
