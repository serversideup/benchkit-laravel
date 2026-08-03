<template>
    <PanelSection title="Hardware">
        <template v-if="geekbench?.url" #aside>
            <a :href="geekbench.url" target="_blank" class="text-sm text-[#94979C] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C] hover:text-[#CECFD2]">
                View on Geekbench &rarr;
            </a>
        </template>

        <p class="mt-2 text-xs text-[#94979C]">&uarr; Higher is better &mdash; {{ subtitle }}</p>

        <div v-if="geekbench" class="mt-6 flex flex-wrap items-end gap-x-10 gap-y-6">
            <div>
                <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                    <img src="/images/results/single-core.png" alt="" class="w-3.5"> Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} single-core
                </p>
                <p class="mt-1 text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ geekbench.single }}</p>
            </div>
            <div>
                <p class="flex items-center gap-1.5 text-sm font-medium text-[#94979C]">
                    <img src="/images/results/multi-core.png" alt="" class="w-3.5"> Geekbench{{ geekbench.version ? ` ${geekbench.version}` : '' }} multi-core
                </p>
                <p class="mt-1 text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ geekbench.multi }}</p>
            </div>
        </div>

        <template v-if="fioRows.length">
            <!-- Mobile: group by block size, each read/write/mixed bar on its
                 own row — the desktop 4-column matrix is too wide for a phone -->
            <div class="mt-6 flex flex-col gap-5 md:hidden">
                <div v-for="row in fioRows" :key="`m-${row.bs}`">
                    <p class="text-xs text-[#CECFD2] font-mono mb-2">Disk I/O <span class="text-[#61656C]">&middot; {{ row.bs }}</span></p>
                    <div class="flex flex-col gap-2.5">
                        <div v-for="column in COLUMNS" :key="`m-${row.bs}-${column.key}`" class="flex items-center gap-3">
                            <span class="w-16 shrink-0 text-xs text-[#94979C]">{{ column.label }}</span>
                            <BarMeter class="flex-1 h-2" :percent="row.widths[column.key]" />
                            <span class="w-[72px] shrink-0 text-right text-xs text-[#CECFD2] font-mono">{{ formatThroughput(row[column.key]) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-7 hidden md:grid gap-x-8 gap-y-3 items-center" style="grid-template-columns: 56px repeat(3, minmax(0, 1fr))">
            <span class="text-xs text-[#94979C]">Disk I/O</span>
            <span v-for="column in COLUMNS" :key="column.key" class="text-sm font-medium text-[#94979C]">{{ column.label }}</span>

            <template v-for="row in fioRows" :key="row.bs">
                <span class="text-xs text-[#CECFD2] font-mono">{{ row.bs }}</span>
                <div v-for="column in COLUMNS" :key="`${row.bs}-${column.key}`" class="flex items-center gap-3"
                    :title="`${row.bs} ${column.label}: ${formatThroughput(row[column.key])}`">
                    <BarMeter class="flex-1 h-2" :percent="row.widths[column.key]" />
                    <span class="w-[72px] shrink-0 text-right text-xs text-[#CECFD2] font-mono">{{ formatThroughput(row[column.key]) }}</span>
                </div>
            </template>
            </div>

            <p v-if="looksCached" class="mt-3 text-sm text-[#94979C]">
                Speeds this high include the OS page cache, not just the physical disk &mdash;
                compare them between runs as relative numbers rather than reading them as real disk speed.
            </p>
        </template>
    </PanelSection>
</template>

<script setup>
import { computed } from 'vue';
import BarMeter from '@/Components/BarMeter.vue';
import PanelSection from '@/Components/PanelSection.vue';
import { formatThroughput } from '@/Composables/useRunSummary';

const props = defineProps({
    hardware: {
        type: Object,
        required: true,
    },
    geekbench: {
        type: Object,
        default: null,
    },
});

const COLUMNS = [
    { key: 'speed_r', label: 'Read' },
    { key: 'speed_w', label: 'Write' },
    { key: 'speed_rw', label: 'Mixed' },
];

const fioRows = computed(() => {
    const rows = props.hardware.fio ?? [];

    const max = Math.max(1, ...rows.flatMap((row) => COLUMNS
        .map(({ key }) => row[key])
        .filter((value) => value != null)));

    return rows.map((row) => ({
        ...row,
        widths: Object.fromEntries(COLUMNS.map(({ key }) => [
            key,
            row[key] != null ? Math.max(2, (row[key] / max) * 100) : 0,
        ])),
    }));
});

// No real disk moves >10 GB/s in this test — beyond that the numbers are
// the OS page cache, and an education-first tool should say so
const looksCached = computed(() => fioRows.value.some((row) => COLUMNS
    .some(({ key }) => (row[key] ?? 0) > 10240)));

const subtitle = computed(() => {
    if( props.geekbench && fioRows.value.length ) {
        return 'Geekbench scores and disk throughput';
    }

    return props.geekbench ? 'Geekbench scores' : 'disk throughput per block size';
});
</script>
