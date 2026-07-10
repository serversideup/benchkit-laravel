<template>
    <section class="py-9">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="text-base font-semibold text-[#F7F7F7]">Network speed test</h2>
            <span class="flex items-center gap-2">
                <span class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">
                    <template v-if="provider">{{ provider }} &rarr; </template>Cloudflare [{{ network.colo }}]
                </span>
                <span v-if="network.asn" class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">AS{{ network.asn }}</span>
            </span>
        </div>

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
    </section>
</template>

<script setup>
import { computed } from 'vue';

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
