<template>
    <PanelSection title="Web server load test">
        <template #aside>
            <span class="flex flex-wrap items-center gap-2">
                <Chip>{{ http.octane ? 'worker mode' : 'classic mode' }}</Chip>
                <Chip v-if="http.duration_seconds">{{ http.duration_seconds }}s</Chip>
                <Chip v-if="http.connections">{{ http.connections }} connections</Chip>
                <Chip v-if="http.io_ms != null">I/O {{ http.io_ms }}ms</Chip>
                <Chip v-if="targetLabel">{{ targetLabel }}</Chip>
            </span>
        </template>

        <p v-if="http.mode === 'app-url'" class="mt-2 text-sm text-[#94979C]">
            Measured through APP_URL &mdash; includes proxy and network overhead, so results aren't directly comparable with loopback runs.
        </p>

        <p class="mt-2 text-xs text-[#61656C]">
            Saturation test &mdash; a fixed connection count held open, reporting max throughput. Tail-latency percentiles are indicative; the &ldquo;Test from your own machine&rdquo; panel has a coordinated-omission-corrected latency command for honest p99s.
        </p>

        <!-- Mobile: one route per block, stacked. The desktop matrix (routes
             as columns) can't survive a phone's width — the big req/s numbers
             alone are wider than a 1fr column there. -->
        <div class="mt-5 flex flex-col divide-y divide-[#22262F] md:hidden">
            <div v-for="route in routes" :key="`m-${route.key}`" class="py-5 first:pt-0 last:pb-0">
                <p class="text-sm font-medium text-[#CECFD2]">{{ route.label }}</p>
                <p class="text-xs text-[#94979C] mt-0.5">{{ route.description }}</p>
                <div class="mt-2">
                    <p class="text-5xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">{{ Math.round(route.data.requests_per_second).toLocaleString() }}</p>
                    <p class="mt-1.5 text-sm text-[#94979C] font-mono">req/s</p>
                </div>
                <div class="mt-4 flex flex-col gap-2.5">
                    <div v-for="percentile in PERCENTILES" :key="`m-${route.key}-${percentile.key}`" class="flex items-center gap-2">
                        <span class="w-24 shrink-0 text-xs text-[#94979C]">{{ percentile.human }} <span class="text-[#61656C]">{{ percentile.key }}</span></span>
                        <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums" :class="route.lowConfidence && percentile.key !== 'p50' ? 'text-[#61656C]' : 'text-[#CECFD2]'">
                            {{ route.values[percentile.key] != null ? `${route.values[percentile.key].toLocaleString()}ms` : '—' }}<template v-if="route.lowConfidence && percentile.key !== 'p50'">*</template>
                        </span>
                        <BarMeter class="flex-1 h-2" :percent="route.values[percentile.key] != null ? route.widths[percentile.key] : null" :color="percentile.color" />
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
            <div v-for="route in routes" :key="`head-${route.key}`" class="self-stretch flex flex-col">
                <p class="text-sm font-medium text-[#CECFD2]">{{ route.label }}</p>
                <p class="text-xs text-[#94979C] mt-0.5">{{ route.description }}</p>
                <!-- mt-auto pins the number to the bottom of the (stretched)
                     cell, so titles align at the top and numbers along one
                     baseline regardless of how many lines each description wraps to -->
                <div class="mt-auto pt-4">
                    <p class="text-4xl text-[#F7F7F7] font-mono font-medium leading-none tabular-nums">{{ Math.round(route.data.requests_per_second).toLocaleString() }}</p>
                    <p class="mt-1.5 text-sm text-[#94979C] font-mono">req/s</p>
                </div>
            </div>

            <template v-for="percentile in PERCENTILES" :key="percentile.key">
                <span class="text-xs text-[#94979C]">{{ percentile.human }} <span class="text-[#61656C]">{{ percentile.key }}</span></span>
                <div v-for="route in routes" :key="`${route.key}-${percentile.key}`" class="flex items-center gap-2"
                    :title="`${route.label} ${percentile.key}: ${route.values[percentile.key] ?? '—'}ms`">
                    <span class="w-[44px] shrink-0 text-left text-xs font-mono tabular-nums" :class="route.lowConfidence && percentile.key !== 'p50' ? 'text-[#61656C]' : 'text-[#CECFD2]'">
                        {{ route.values[percentile.key] != null ? `${route.values[percentile.key].toLocaleString()}ms` : '—' }}<template v-if="route.lowConfidence && percentile.key !== 'p50'">*</template>
                    </span>
                    <BarMeter class="flex-1 h-2" :percent="route.values[percentile.key] != null ? route.widths[percentile.key] : null" :color="percentile.color" />
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

        <p v-if="anyLowConfidence" class="mt-4 text-xs text-[#61656C]">
            * Fewer than 1,000 requests recorded for that route, so its p95/p99 are rough — raise the duration for a firmer tail.
        </p>
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

// Ordered by real-world representativeness: DB read is the closest thing to
// an actual Laravel page, static is the framework ceiling, and io models a
// request dominated by one outbound call — where PHP-FPM and worker mode
// converge. (io's description carries the actual delay, added in `routes`.)
const ROUTES = {
    db_read: { label: 'DB read', description: '20 rows queried per request' },
    json: { label: 'JSON API', description: '25-item JSON payload' },
    static: { label: 'Static', description: 'Framework baseline — no database' },
    io: { label: 'I/O-bound', description: 'Simulated outbound call' },
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

    return entries.map(([key, data]) => {
        const values = {};
        const widths = {};

        // Bars scale within each route (to its own slowest percentile), not
        // across all routes: the I/O route's latency is several times the
        // others', so a shared scale would flatten every other bar into an
        // unreadable stub stranded from its value. The absolute ms sit beside
        // each bar for cross-route comparison.
        const routeMax = Math.max(1, ...PERCENTILES
            .map(({ key: percentile }) => data[`${percentile}_ms`])
            .filter((value) => value != null));

        PERCENTILES.forEach(({ key: percentile }) => {
            const raw = data[`${percentile}_ms`];
            values[percentile] = raw != null ? Math.round(raw) : null;
            widths[percentile] = raw != null ? Math.max(2, (raw / routeMax) * 100) : 0;
        });

        return {
            key,
            label: ROUTES[key]?.label ?? key,
            description: key === 'io'
                ? `Simulated ~${props.http.io_ms ?? 100}ms outbound call`
                : ROUTES[key]?.description ?? '',
            data,
            values,
            widths,
            // Too few samples and p95/p99 are just the 2nd/1st-slowest hit —
            // flag the tail as rough rather than presenting it as firm.
            lowConfidence: (data.total_requests ?? Infinity) < 1000,
        };
    });
});

const anyFailures = computed(() => routes.value.some((route) => route.data.success_rate != null && route.data.success_rate < 1));

const anyLowConfidence = computed(() => routes.value.some((route) => route.lowConfidence));
</script>
