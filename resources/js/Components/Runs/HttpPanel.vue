<template>
    <PanelSection eyebrow="oha" title="Web server load test">
        <template #aside>
            <span class="flex flex-wrap items-center gap-2">
                <Chip>{{ http.octane ? 'worker mode' : 'classic mode' }}</Chip>
                <Chip>{{ generatorLabel }}</Chip>
                <Chip v-if="http.io_ms != null">I/O {{ http.io_ms }}ms</Chip>
                <Chip v-if="http.workers">{{ http.workers }} workers</Chip>
                <Chip v-if="targetLabel">{{ targetLabel }}</Chip>
            </span>
        </template>

        <p class="mt-3 max-w-[74ch] text-xs text-[#61656C] leading-relaxed">
            Concurrency was raised until throughput stopped improving. Response times were measured separately, at
            about {{ latencyShare }} of that rate, so they include no queue a real visitor would not also hit.
            <a :href="LOAD_TEST_DOCS" target="_blank" rel="noopener"
                class="text-[#94979C] underline underline-offset-4 decoration-[#373A41] hover:text-[#CECFD2] hover:decoration-[#61656C] transition-colors duration-200">Learn
                more</a>
        </p>

        <!-- One row per route. The x-axes line up down the column, which keeps
             the cross-route reading a shared chart would flatten. -->
        <div class="mt-6 flex flex-col divide-y divide-[#22262F]">
            <div v-for="route in routes" :key="route.key"
                class="grid grid-cols-1 gap-x-7 gap-y-5 py-6 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_300px] md:items-center">
                <div class="min-w-0">
                    <p class="text-[#F7F7F7]">{{ route.label }}</p>
                    <p class="mt-1 text-sm text-[#94979C]">{{ route.description }}</p>

                    <div class="mt-4 grid grid-cols-2 gap-x-6">
                        <div class="min-w-0">
                            <p class="text-[11px] text-[#61656C] font-mono uppercase tracking-wide">Response time</p>
                            <p v-if="route.latency" class="mt-1 text-2xl text-[#F7F7F7] font-mono">
                                {{ formatMs(route.latency.p50_ms) }}
                            </p>
                            <p v-else class="mt-1 text-2xl text-[#61656C] font-mono">&mdash;</p>
                            <p v-if="route.latency" class="mt-1 text-xs font-mono"
                                :class="route.tailGrowsUnderLoad ? 'text-[#F79009]' : 'text-[#94979C]'">
                                p95 {{ formatMs(route.latency.p95_ms) }} · p99 {{ formatMs(route.latency.p99_ms) }}<span
                                    v-if="route.lowConfidence">*</span>
                            </p>
                            <p v-if="route.tailGrowsUnderLoad" class="mt-1 max-w-[26ch] text-xs text-[#F79009] font-mono leading-relaxed">
                                {{ formatMs(route.idleP95) }} when idle
                            </p>
                        </div>

                        <div class="min-w-0">
                            <p class="text-[11px] text-[#61656C] font-mono uppercase tracking-wide">Max throughput</p>
                            <p class="mt-1 text-2xl text-[#F7F7F7] font-mono whitespace-nowrap">
                                <span v-if="route.isFloor" class="text-[#F79009]">&ge;</span>{{ formatRps(route.rps) }}
                                <span class="text-sm text-[#94979C]">req/s</span>
                            </p>
                            <p v-if="route.isFloor" class="mt-1 text-xs text-[#F79009] font-mono">
                                still climbing at {{ route.topLevel }} — a floor
                            </p>
                            <p v-else-if="route.peakConcurrency != null" class="mt-1 text-xs text-[#94979C] font-mono">
                                at {{ route.peakConcurrency }} concurrent
                            </p>
                            <p v-if="route.failing" class="mt-1 text-xs text-[#F97066] font-mono">
                                {{ (route.successRate * 100).toFixed(1) }}% success
                            </p>
                        </div>
                    </div>
                </div>

                <LoadCurveChart :points="route.curve" :knee="route.knee" :label="route.label"
                    :predicted-rps="route.predictedRps" :predicted-label="route.predictedLabel" />
            </div>
        </div>

        <p v-if="anyLowConfidence" class="mt-6 text-xs text-[#61656C] font-mono">
            * Fewer than 1,000 requests were timed on that route, so its p95 and p99 are rough.
        </p>
    </PanelSection>
</template>

<script setup>
import { computed } from 'vue';
import { tailUnderLoad } from '@shared/run/conditions.mjs';
import Chip from '@/Components/Chip.vue';
import PanelSection from '@/Components/PanelSection.vue';
import LoadCurveChart from '@/Components/Runs/LoadCurveChart.vue';
import { httpTargetLabel } from '@/Composables/useRunSummary';

const props = defineProps({
    http: {
        type: Object,
        required: true,
    },
});

const LOAD_TEST_DOCS = 'https://serversideup.net/open-source/benchkit/docs/benchmarks';

const targetLabel = computed(() => httpTargetLabel(props.http.mode));

const strained = computed(() => tailUnderLoad(props.http));

const latencyShare = computed(() => `${Math.round((props.http.latency_load ?? 0.7) * 100)}%`);

// Where the load came from. Snapshots from before external mode carry no
// generator block, which provably makes them self-tests.
const generatorLabel = computed(() => {
    if ((props.http.generator?.mode ?? 'self') !== 'external') {
        return 'self-tested';
    }

    const rtt = props.http.generator?.rtt_ms;

    return rtt != null ? `external load · ${rtt}ms RTT` : 'external load';
});

// Ordered as a ladder: each route adds one thing to the one before it —
// serialization, then a database, then a blocking wait. Read top to bottom the
// deltas are the story ("what does a query cost me?").
const ROUTES = {
    static: { label: 'Static', description: 'Framework baseline — no database' },
    json: { label: 'JSON API', description: '25-item JSON payload' },
    db_read: { label: 'DB read', description: '20 rows queried per request' },
    io: { label: 'I/O-bound', description: 'Simulated outbound call' },
};

/** What one request costs with nothing queued. The median, not the tail: one short window's tail is a couple of requests. */
const idleTail = (curve) => curve.find((point) => point.concurrency === 1)?.p50_ms ?? null;

const formatRps = (value) => value == null ? '—' : Math.round(value).toLocaleString();

const formatMs = (value) => {
    if (value == null) {
        return '—';
    }

    return value < 10 ? `${value.toFixed(1)}ms` : `${Math.round(value)}ms`;
};

const routes = computed(() => Object.keys(ROUTES)
    .map((key) => [key, props.http.routes?.[key]])
    .filter(([, data]) => data?.throughput?.requests_per_second != null)
    .map(([key, data]) => {
        const curve = data.curve ?? [];
        const ceiling = key === 'io' ? props.http.pool_ceiling : null;

        return {
            key,
            label: ROUTES[key].label,
            description: key === 'io'
                ? `Simulated ~${props.http.io_ms ?? 100}ms outbound call`
                : ROUTES[key].description,
            curve,
            // The peak is the headline number; the bend is what the chart
            // marks. Marking the peak put the dot at the far right on a route
            // whose plateau was long, so the grey "past here you are only
            // adding queue" segment never appeared at all.
            peakConcurrency: data.throughput.concurrency ?? null,
            knee: data.throughput.knee_concurrency ?? data.throughput.concurrency ?? null,
            rps: data.throughput.requests_per_second,
            successRate: data.throughput.success_rate,
            failing: data.throughput.success_rate != null && data.throughput.success_rate < 1,
            // The sweep never saw this route flatten, so the figure is the most
            // BenchKit could ask for rather than the most the machine can give.
            isFloor: data.throughput.saturated === false,
            topLevel: curve.length ? curve[curve.length - 1].concurrency : null,
            latency: data.latency ?? null,
            // Only the I/O route has a ceiling that can be predicted rather
            // than measured. Drawing the same line on the others would be
            // arithmetic about nothing.
            predictedRps: ceiling?.predicted_rps ?? null,
            predictedLabel: ceiling
                ? `${ceiling.workers} workers ÷ ${ceiling.io_ms}ms = ${Math.round(ceiling.predicted_rps).toLocaleString()}/s`
                : '',
            // Too few samples and p95/p99 are just the 2nd/1st-slowest hit —
            // flag the tail as rough rather than presenting it as firm.
            lowConfidence: (data.latency?.total_requests ?? Infinity) < 1000,
            // A loaded tail several times the uncontended one appeared because
            // of the load, which makes it the server's and not the path's.
            idleP95: idleTail(curve),
            tailGrowsUnderLoad: strained.value.some((route) => route.key === key),
        };
    }));

const anyLowConfidence = computed(() => routes.value.some((route) => route.lowConfidence));
</script>
