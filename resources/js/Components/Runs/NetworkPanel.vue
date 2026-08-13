<template>
    <PanelSection eyebrow="cfspeedtest" title="Network speed test">
        <template #aside>
            <span class="flex flex-wrap items-center gap-2">
                <Chip><template v-if="provider">{{ provider }} &rarr; </template>Cloudflare [{{ network.colo }}]</Chip>
                <Chip v-if="network.asn">AS{{ network.asn }}</Chip>
            </span>
        </template>

        <p class="mt-2 text-sm text-[#94979C]">Measured from your server to Cloudflare's nearest edge &mdash; this latency rides on every external request your app makes.</p>

        <div class="mt-6 flex flex-wrap items-end gap-x-10 gap-y-6">
            <div v-for="stat in stats" :key="stat.label">
                <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                    <img :src="stat.icon" alt="" class="w-3.5"> {{ stat.label }}
                </p>
                <p class="flex items-baseline gap-2 mt-1">
                    <span class="text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ stat.value }}</span>
                    <span class="text-sm text-[#94979C] font-mono">{{ stat.unit }}</span>
                </p>
            </div>
        </div>
    </PanelSection>
</template>

<script setup>
import { computed } from 'vue';
import Chip from '@/Components/Chip.vue';
import PanelSection from '@/Components/PanelSection.vue';

const props = defineProps({
    network: {
        type: Object,
        required: true,
    },
    provider: {
        type: String,
        default: null,
    },
});

const round = (value) => value == null ? '—' : parseFloat(value).toFixed(0);

const stats = computed(() => [
    { label: 'Download', unit: 'mbps', value: round(props.network.download), icon: '/images/results/download-cloud.png' },
    { label: 'Upload', unit: 'mbps', value: round(props.network.upload), icon: '/images/results/upload-cloud.png' },
    { label: 'Latency', unit: 'ms', value: round(props.network.latency), icon: '/images/results/latency-switch.png' },
]);
</script>
