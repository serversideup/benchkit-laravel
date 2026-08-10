<template>
    <div>
        <!-- The real values, not a description of them. Built from the document
             the server is about to send, so if it isn't here it isn't being
             published. -->
        <dl v-if="metrics.length" class="grid grid-cols-3 gap-px overflow-hidden rounded-lg bg-[#22262F]">
            <div v-for="metric in metrics" :key="metric.label" class="min-w-0 bg-[#13161B] px-3 py-2.5">
                <dd class="truncate font-mono text-base text-[#F7F7F7] tabular-nums">{{ metric.value }}</dd>
                <dt class="mt-0.5 truncate text-[11px] text-[#61656C]">{{ metric.label }}</dt>
            </div>
        </dl>

        <dl class="mt-3 flex flex-col gap-1.5">
            <div v-for="fact in facts" :key="fact.label" class="flex min-w-0 items-baseline gap-3 text-xs">
                <dt class="w-16 shrink-0 text-[#61656C]">{{ fact.label }}</dt>
                <dd class="min-w-0 font-mono text-[#CECFD2]">{{ fact.value }}</dd>
            </div>
        </dl>

        <p class="mt-4 text-[11px] font-medium tracking-wide text-[#61656C] uppercase">Never sent</p>
        <ul class="mt-1.5 flex flex-col gap-1 text-xs leading-snug text-[#94979C]">
            <li v-for="item in WITHHELD" :key="item" class="flex gap-2">
                <span class="text-[#373A41]">&mdash;</span>{{ item }}
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatMs, hostDetailsLine } from '@/Composables/useRunSummary';

const props = defineProps({
    // The submission document from GET /runs/{id}/submission
    document: {
        type: Object,
        required: true,
    },
});

// Mirrors the table in docs/content/docs/5.community-results — short enough to
// actually be read at the moment of deciding.
const WITHHELD = [
    'Console logs (they hold your public IP)',
    'APP_URL and internal hostnames',
    'Raw YABS output (IP, ISP, city)',
    'Network ASN and Cloudflare colo',
    'Your opcache.preload path',
];

const route = (key) => props.document.benchmarks?.http?.routes?.[key] ?? null;

// The three numbers a gallery card leads with, in the same order of preference
// the run summary uses — whichever of them this run actually measured.
const metrics = computed(() => {
    const hero = ['db_read', 'json', 'static'].map(route).find((row) => row?.requests_per_second != null);
    const benchmarks = props.document.benchmarks ?? {};

    return [
        hero && { label: 'requests/sec', value: Math.round(hero.requests_per_second).toLocaleString() },
        hero?.p95_ms != null && { label: 'p95 latency', value: formatMs(hero.p95_ms) },
        benchmarks.php?.headline?.read?.milliseconds != null
            && { label: 'PHP read', value: formatMs(benchmarks.php.headline.read.milliseconds) },
        benchmarks.geekbench && { label: 'Geekbench multi', value: benchmarks.geekbench.multi?.toLocaleString() },
        benchmarks.cfspeedtest?.download_mbps != null
            && { label: 'download', value: `${Math.round(benchmarks.cfspeedtest.download_mbps)} Mbps` },
    ].filter(Boolean).slice(0, 3);
});

const facts = computed(() => {
    const server = props.document.environment?.server ?? {};
    const php = props.document.environment?.php ?? {};
    const laravelVersion = props.document.environment?.laravel?.environment?.laravel_version;

    return [
        {
            label: 'Host',
            value: hostDetailsLine(props.document.meta ?? {}) || 'Not given — will show as Self-Hosted',
        },
        server.cpu_model && {
            label: 'Machine',
            value: [server.cpu_model, server.cpu_cores && `${server.cpu_cores} cores`, server.ram].filter(Boolean).join(' · '),
        },
        {
            label: 'Stack',
            value: [
                php.php_variation,
                php.php_version && `PHP ${php.php_version}`,
                laravelVersion && `Laravel ${laravelVersion}`,
                php.octane ? 'Octane' : null,
            ].filter(Boolean).join(' · '),
        },
        props.document.stages_completed?.length && {
            label: 'Stages',
            value: props.document.stages_completed.join(', '),
        },
    ].filter((fact) => fact && fact.value);
});
</script>
