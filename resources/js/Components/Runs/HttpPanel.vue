<template>
    <PanelSection title="Web server load test">
        <template #aside>
            <span class="flex flex-wrap items-center gap-2">
                <Chip>{{ http.octane ? 'worker mode' : 'classic mode' }}</Chip>
                <Chip v-if="http.duration_seconds">{{ http.duration_seconds }}s</Chip>
                <Chip v-if="http.connections">{{ http.connections }} connections</Chip>
                <Chip v-if="targetLabel">{{ targetLabel }}</Chip>
            </span>
        </template>

        <p v-if="http.mode === 'app-url'" class="mt-2 text-sm text-[#94979C]">
            Measured through APP_URL &mdash; includes proxy and network overhead, so results aren't directly comparable with loopback runs.
        </p>

        <!-- Mobile: one route per block, stacked. The desktop matrix (routes
             as columns) can't survive a phone's width — the big req/s numbers
             alone are wider than a 1fr column there. -->
        <div class="mt-5 flex flex-col divide-y divide-[#22262F] md:hidden">
            <div v-for="route in routes" :key="`m-${route.key}`" class="py-5 first:pt-0 last:pb-0">
                <p class="text-sm font-medium text-[#CECFD2]">{{ route.label }}</p>
                <p class="text-xs text-[#94979C] mt-0.5">{{ route.description }}</p>
                <p class="flex items-baseline gap-2 mt-2">
                    <span class="text-5xl text-[#F7F7F7] font-mono font-medium leading-none">{{ Math.round(route.data.requests_per_second).toLocaleString() }}</span>
                    <span class="text-base text-[#94979C] font-mono">req/s</span>
                </p>
                <div class="mt-4 flex flex-col gap-2.5">
                    <div v-for="percentile in PERCENTILES" :key="`m-${route.key}-${percentile.key}`" class="flex items-center gap-3">
                        <span class="w-24 shrink-0 text-xs text-[#94979C]">{{ percentile.human }} <span class="text-[#61656C]">{{ percentile.key }}</span></span>
                        <BarMeter class="flex-1 h-2" :percent="route.values[percentile.key] != null ? route.widths[percentile.key] : null" :color="percentile.color" />
                        <span class="w-[64px] shrink-0 text-right text-xs text-[#CECFD2] font-mono">
                            {{ route.values[percentile.key] != null ? `${route.values[percentile.key].toLocaleString()}ms` : '—' }}
                        </span>
                    </div>
                </div>
                <p v-if="route.data.success_rate != null && route.data.success_rate < 1" class="mt-3 text-sm font-mono text-[#F97066]">
                    {{ (route.data.success_rate * 100).toFixed(1) }}% success
                </p>
            </div>
        </div>

        <!-- Desktop: the full matrix — routes across, percentiles down -->
        <div class="mt-7 hidden md:grid gap-x-8 gap-y-3 items-center" :style="`grid-template-columns: 96px repeat(${routes.length}, minmax(0, 1fr))`">
            <div class="self-end"></div>
            <div v-for="route in routes" :key="`head-${route.key}`" class="self-end pb-2">
                <p class="text-sm font-medium text-[#CECFD2]">{{ route.label }}</p>
                <p class="text-xs text-[#94979C] mt-0.5">{{ route.description }}</p>
                <p class="flex items-baseline gap-2 mt-3">
                    <span class="text-6xl text-[#F7F7F7] font-mono font-medium leading-none">{{ Math.round(route.data.requests_per_second).toLocaleString() }}</span>
                    <span class="text-lg text-[#94979C] font-mono">req/s</span>
                </p>
            </div>

            <template v-for="percentile in PERCENTILES" :key="percentile.key">
                <span class="text-xs text-[#94979C]">{{ percentile.human }} <span class="text-[#61656C]">{{ percentile.key }}</span></span>
                <div v-for="route in routes" :key="`${route.key}-${percentile.key}`" class="flex items-center gap-3"
                    :title="`${route.label} ${percentile.key}: ${route.values[percentile.key] ?? '—'}ms`">
                    <BarMeter class="flex-1 h-2" :percent="route.values[percentile.key] != null ? route.widths[percentile.key] : null" :color="percentile.color" />
                    <span class="w-[64px] shrink-0 text-right text-xs text-[#CECFD2] font-mono">
                        {{ route.values[percentile.key] != null ? `${route.values[percentile.key].toLocaleString()}ms` : '—' }}
                    </span>
                </div>
            </template>

            <template v-if="anyFailures">
                <span></span>
                <div v-for="route in routes" :key="`success-${route.key}`">
                    <p v-if="route.data.success_rate != null && route.data.success_rate < 1" class="text-sm font-mono text-[#F97066]">
                        {{ (route.data.success_rate * 100).toFixed(1) }}% success
                    </p>
                </div>
            </template>
        </div>
    </PanelSection>
</template>

<script setup>
import { computed } from 'vue';
import BarMeter from '@/Components/BarMeter.vue';
import Chip from '@/Components/Chip.vue';
import PanelSection from '@/Components/PanelSection.vue';
import { httpTargetLabel } from '@/Composables/useRunSummary';

const props = defineProps({
    http: {
        type: Object,
        required: true,
    },
});

const targetLabel = computed(() => httpTargetLabel(props.http.mode));

// Ordered by real-world representativeness: DB read is the closest thing
// to an actual Laravel page; static is the framework ceiling
const ROUTES = {
    db_read: { label: 'DB read', description: '20 rows queried per request' },
    json: { label: 'JSON API', description: '25-item JSON payload' },
    static: { label: 'Static', description: 'Framework baseline — no database' },
};

// Gray = a typical request, amber = the tail. Series identity like
// Nightwatch's AVG/MAX pair — true no matter how fast or slow the values
// are, so a blazing 1ms tail never gets painted as an error.
const PERCENTILES = [
    { key: 'p50', human: 'Typical', color: '#94979C' },
    { key: 'p95', human: 'Slowest 5%', color: '#F79009' },
    { key: 'p99', human: 'Slowest 1%', color: '#F79009' },
];

const routes = computed(() => {
    const entries = Object.keys(ROUTES)
        .map((key) => [key, props.http.routes?.[key]])
        .filter(([, data]) => data && data.requests_per_second != null);

    const maxLatency = Math.max(1, ...entries.flatMap(([, data]) => PERCENTILES
        .map(({ key }) => data[`${key}_ms`])
        .filter((value) => value != null)));

    return entries.map(([key, data]) => {
        const values = {};
        const widths = {};

        PERCENTILES.forEach(({ key: percentile }) => {
            const raw = data[`${percentile}_ms`];
            values[percentile] = raw != null ? Math.round(raw) : null;
            widths[percentile] = raw != null ? Math.max(2, (raw / maxLatency) * 100) : 0;
        });

        return {
            key,
            label: ROUTES[key]?.label ?? key,
            description: ROUTES[key]?.description ?? '',
            data,
            values,
            widths,
        };
    });
});

const anyFailures = computed(() => routes.value.some((route) => route.data.success_rate != null && route.data.success_rate < 1));
</script>
