<template>
    <section class="py-9">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="text-base font-semibold text-[#F7F7F7]">Web server load test</h2>
            <span class="flex items-center gap-2">
                <span class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">{{ http.mode === 'octane' ? 'worker mode' : 'standard mode' }}</span>
                <span v-if="http.duration_seconds" class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">{{ http.duration_seconds }}s</span>
                <span v-if="http.connections" class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">{{ http.connections }} connections</span>
            </span>
        </div>

        <div class="mt-7 grid gap-x-8 gap-y-3 items-center" :style="`grid-template-columns: 96px repeat(${routes.length}, minmax(0, 1fr))`">
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
                    <span class="flex-1 h-2 rounded-sm bg-[#22262F] overflow-hidden">
                        <span v-if="route.values[percentile.key] != null" class="block h-full rounded-r-[4px]"
                            :style="`width: ${route.widths[percentile.key]}%; background-color: ${percentile.color};`"></span>
                    </span>
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
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    http: {
        type: Object,
        required: true,
    },
});

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
