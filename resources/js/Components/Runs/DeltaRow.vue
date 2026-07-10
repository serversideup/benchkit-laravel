<template>
    <tr class="border-b border-[#22262F] last:border-b-0 text-sm">
        <td class="px-4 py-3 text-[#94979C]">{{ delta.label }}</td>
        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-3">
                <BarMeter class="hidden sm:block w-24 h-1.5" :percent="barA" color="#61656C" />
                <span class="text-[#94979C] font-mono whitespace-nowrap">{{ formatValue(delta.a) }}<span v-if="delta.unit" class="text-[#61656C] ml-1">{{ delta.unit }}</span></span>
            </div>
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-3">
                <BarMeter class="hidden sm:block w-24 h-1.5" :percent="barB" :color="barColorB" />
                <span class="text-[#F7F7F7] font-mono whitespace-nowrap">{{ formatValue(delta.b) }}<span v-if="delta.unit" class="text-[#61656C] ml-1">{{ delta.unit }}</span></span>
            </div>
        </td>
        <td class="px-4 py-3 text-right font-mono whitespace-nowrap" :class="deltaColor">
            {{ formatDelta }}
        </td>
    </tr>
</template>

<script setup>
import { computed } from 'vue';
import BarMeter from '@/Components/BarMeter.vue';

const props = defineProps({
    delta: {
        type: Object,
        required: true,
    },
});

const formatValue = (value) => {
    return Math.abs(value) >= 100 ? Math.round(value).toLocaleString() : parseFloat(value.toFixed(2)).toLocaleString();
};

// Bars share a per-row scale (the larger of the pair fills the track), so
// each row reads as a direct A-vs-B ratio. Length is magnitude; the verdict
// color lives on B's bar and the change column.
const max = computed(() => Math.max(Math.abs(props.delta.a), Math.abs(props.delta.b), 1e-9));
const barA = computed(() => Math.max(2, (Math.abs(props.delta.a) / max.value) * 100));
const barB = computed(() => Math.max(2, (Math.abs(props.delta.b) / max.value) * 100));

const barColorB = computed(() => {
    if( props.delta.improved === null ) {
        return '#94979C';
    }

    return props.delta.improved ? '#47CD89' : '#F97066';
});

const deltaColor = computed(() => {
    if( props.delta.improved === null ) {
        return 'text-[#61656C]';
    }

    return props.delta.improved ? 'text-[#47CD89]' : 'text-[#F97066]';
});

const formatDelta = computed(() => {
    if( props.delta.improved === null ) {
        return '~even';
    }

    const sign = props.delta.delta > 0 ? '+' : '';
    const percent = props.delta.percent != null ? ` (${sign}${props.delta.percent.toFixed(1)}%)` : '';
    const magnitude = Math.abs(props.delta.delta) >= 100 ? Math.round(props.delta.delta).toLocaleString() : parseFloat(props.delta.delta.toFixed(2));

    return `${sign}${magnitude}${percent}`;
});
</script>
