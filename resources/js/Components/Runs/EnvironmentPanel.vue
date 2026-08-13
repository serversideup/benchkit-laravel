<template>
    <PanelSection eyebrow="Configuration" title="Environment">
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-3 text-sm">
            <div class="flex flex-col gap-3">
                <div v-for="fact in stackFacts" :key="fact.label" class="grid grid-cols-[110px_1fr] gap-3">
                    <span class="text-[#94979C]">{{ fact.label }}</span>
                    <span class="text-[#F7F7F7] font-mono break-words">{{ fact.value }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <div v-for="fact in machineFacts" :key="fact.label" class="grid grid-cols-[110px_1fr] gap-3">
                    <span class="text-[#94979C]">{{ fact.label }}</span>
                    <span class="text-[#F7F7F7] font-mono break-words">{{ fact.value }}</span>
                </div>
            </div>
        </div>

        <div v-if="tuningFacts.length" class="mt-6 pt-6 border-t border-[#22262F]">
            <p class="text-xs text-[#61656C] font-mono uppercase tracking-wider mb-3">Tuning</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2 text-sm">
                <div v-for="fact in tuningFacts" :key="fact.label" class="grid grid-cols-[minmax(120px,auto)_1fr] gap-3">
                    <span class="text-[#94979C] font-mono break-words">{{ fact.label }}</span>
                    <span class="text-[#F7F7F7] font-mono break-words">{{ fact.value }}</span>
                </div>
            </div>
        </div>
    </PanelSection>
</template>

<script setup>
import { computed } from 'vue';
import PanelSection from '@/Components/PanelSection.vue';
import { formatCapacity } from '@/Composables/useRunSummary';

const props = defineProps({
    environment: {
        type: Object,
        required: true,
    },
    hardware: {
        type: Object,
        default: null,
    },
});

const bool = (value) => value ? 'on' : 'off';

const formatRam = (ram) => {
    const match = /^([\d.]+)\s*MB$/i.exec(ram ?? '');

    if( !match ) {
        return ram;
    }

    const megabytes = parseFloat(match[1]);

    return megabytes >= 1024 ? `${(megabytes / 1024).toFixed(1)} GB` : `${Math.round(megabytes)} MB`;
};

const present = (facts) => facts.filter((fact) => fact.value != null && fact.value !== '');

const MODE_LABELS = {
    'worker': 'Worker (persistent)',
    'process-per-request': 'Process per request',
};

// What served the requests. Kept apart from tuning below because it answers a
// different question — "what was this?" rather than "how was it set up?" — and
// because every row here is filled in by detection, so a runtime BenchKit has
// never seen still fills in most of it rather than rendering blanks.
const stackFacts = computed(() => {
    const environment = props.environment;
    const runtime = environment.php?.runtime ?? {};

    return present([
        { label: 'Server', value: environment.php?.php_variation || runtime.server },
        { label: 'Mode', value: MODE_LABELS[runtime.mode] ?? null },
        // Labelled with what it counts: 20 FPM children and 8 FrankenPHP
        // threads are both "workers" and are not the same quantity.
        {
            label: 'Workers',
            value: runtime.workers
                ? `${runtime.workers}${runtime.workers_source ? ` (${runtime.workers_source})` : ''}`
                : null,
        },
        // php_sapi_name() says "fpm-fcgi" for nginx and Apache alike, so this
        // is the only thing that tells the two images apart.
        {
            label: 'Web server',
            value: runtime.front_end
                ? [runtime.front_end, runtime.front_end_version].filter(Boolean).join(' ')
                : null,
        },
        { label: 'SAPI', value: environment.php?.php_server_api },
        { label: 'PHP', value: environment.php?.php_version },
        { label: 'Laravel', value: environment.laravel?.environment?.laravel_version },
        { label: 'Database', value: environment.laravel?.drivers?.database },
    ]);
});

const machineFacts = computed(() => {
    const environment = props.environment;
    const cores = environment.server?.cpu_cores;

    return present([
        {
            label: 'CPU',
            value: environment.server?.cpu_model
                ? `${environment.server.cpu_model}${cores ? ` (${cores} cores)` : ''}`
                : null,
        },
        { label: 'RAM', value: formatRam(environment.server?.ram) },
        { label: 'Disk', value: formatCapacity(props.hardware?.mem?.disk, props.hardware?.mem?.disk_units) },
        { label: 'OS', value: environment.server?.os },
        { label: 'Build', value: environment.build_version },
    ]);
});

/**
 * Everything an operator chose — the reproducibility answer to "which settings
 * produced these numbers", and what a reader comparing two runs on the same
 * hardware is actually looking for.
 *
 * The server's own directives render through the same generic label/value pass
 * as the ini values. That is deliberate: the previous version hardcoded the two
 * FPM keys it knew about, so a FrankenPHP thread count or an Octane worker
 * limit had nowhere to appear even once something recorded it.
 */
const tuningFacts = computed(() => {
    const php = props.environment?.php ?? {};

    const display = (value) => (value === false || value === '' ? 'off' : String(value));

    return present([
        { label: 'OPcache', value: php.op_cache != null ? bool(php.op_cache) : null },
        { label: 'OPcache JIT', value: php.op_cache_jit },
        { label: 'Memory limit', value: php.memory_limit },
        {
            label: 'Debug mode',
            value: props.environment?.laravel?.environment?.debug_mode === true ? 'on' : null,
        },
        ...Object.entries(php.runtime?.settings ?? {}).map(([label, value]) => ({ label, value: display(value) })),
        ...Object.entries(php.ini ?? {})
            .filter(([key]) => !SUMMARY_INI.includes(key))
            .map(([label, value]) => ({ label, value: display(value) })),
    ]);
});

// Shown in the Tuning group already, so not repeated from the raw ini dump.
const SUMMARY_INI = ['opcache.enable', 'opcache.jit', 'memory_limit'];
</script>
