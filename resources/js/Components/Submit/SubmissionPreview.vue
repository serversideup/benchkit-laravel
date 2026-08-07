<template>
    <div>
        <!-- Exactly what the gallery will show, built from the document the
             server is about to send — not from the run in the browser. If it
             isn't here, it isn't being published. -->
        <div class="rounded-xl border border-[#22262F] bg-[#13161B] overflow-hidden">
            <div class="px-4 py-3 border-b border-[#22262F]">
                <p class="text-sm font-medium text-[#F7F7F7] truncate">{{ document.meta?.label }}</p>
                <p class="mt-0.5 text-xs font-mono text-[#61656C] truncate">{{ hostLine || 'No host details — will appear as Self-Hosted' }}</p>
            </div>

            <dl v-if="metrics.length" class="grid grid-cols-3 divide-x divide-[#22262F] border-b border-[#22262F]">
                <div v-for="metric in metrics" :key="metric.label" class="px-4 py-3 min-w-0">
                    <dd class="font-mono text-lg text-[#F7F7F7] tabular-nums truncate">{{ metric.value }}</dd>
                    <dt class="mt-0.5 text-[11px] text-[#61656C] truncate">{{ metric.label }}</dt>
                </div>
            </dl>

            <dl class="px-4 py-3 flex flex-col gap-1.5">
                <div v-for="fact in facts" :key="fact.label" class="flex items-baseline gap-3 text-xs min-w-0">
                    <dt class="text-[#61656C] w-20 shrink-0">{{ fact.label }}</dt>
                    <dd class="font-mono text-[#CECFD2] truncate">{{ fact.value }}</dd>
                </div>
            </dl>
        </div>

        <!-- The privacy work in this app is careful and completely invisible to
             the person it protects. Saying it plainly, next to the button, is
             the point. -->
        <div class="mt-4 grid grid-cols-2 gap-px rounded-xl border border-[#22262F] bg-[#22262F] overflow-hidden">
            <div class="bg-[#0C0E12] px-3.5 py-3">
                <p class="flex items-center gap-1.5 text-xs font-medium text-[#CECFD2]">
                    <IconShield :size="13" class="shrink-0 text-[#47CD89]" />
                    Published
                </p>
                <ul class="mt-2 flex flex-col gap-1 text-[11px] leading-snug text-[#94979C]">
                    <li v-for="item in PUBLISHED" :key="item">{{ item }}</li>
                </ul>
            </div>
            <div class="bg-[#0C0E12] px-3.5 py-3">
                <p class="text-xs font-medium text-[#CECFD2]">Never sent</p>
                <ul class="mt-2 flex flex-col gap-1 text-[11px] leading-snug text-[#94979C]">
                    <li v-for="item in WITHHELD" :key="item">{{ item }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import { formatMs, hostDetailsLine } from '@/Composables/useRunSummary';

const props = defineProps({
    // The submission document from GET /runs/{id}/submission
    document: {
        type: Object,
        required: true,
    },
});

// Mirrors the table in docs/content/docs/5.community-results — kept short
// enough to actually be read at the moment of deciding.
const PUBLISHED = [
    'CPU, RAM, and OS',
    'PHP, Laravel, and image variation',
    'OPcache, JIT, and FPM pool settings',
    'The benchmark results',
    'The host details you typed',
];

const WITHHELD = [
    'Console logs (they hold your public IP)',
    'APP_URL and internal hostnames',
    'Raw YABS output (IP, ISP, city)',
    'Network ASN and Cloudflare colo',
    'Your opcache.preload path',
];

const hostLine = computed(() => hostDetailsLine(props.document.meta ?? {}));

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
        server.cpu_model && {
            label: 'Machine',
            value: [server.cpu_model, server.cpu_cores && `${server.cpu_cores} cores`, server.ram].filter(Boolean).join(' · '),
        },
        { label: 'Stack', value: [
            php.php_variation,
            php.php_version && `PHP ${php.php_version}`,
            laravelVersion && `Laravel ${laravelVersion}`,
            php.octane ? 'Octane' : null,
        ].filter(Boolean).join(' · ') },
        props.document.stages_completed?.length && {
            label: 'Stages',
            value: props.document.stages_completed.join(', '),
        },
    ].filter((fact) => fact && fact.value);
});
</script>
