<template>
    <section class="py-9">
        <h2 class="text-base font-semibold text-[#F7F7F7]">Environment</h2>

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
    </section>
</template>

<script setup>
import { computed } from 'vue';
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

const stackFacts = computed(() => {
    const environment = props.environment;

    return present([
        { label: 'Server', value: environment.php?.php_variation || environment.php?.php_server_api },
        { label: 'PHP', value: environment.php?.php_version },
        { label: 'Laravel', value: environment.laravel?.environment?.laravel_version },
        { label: 'Octane', value: environment.php?.octane != null ? bool(environment.php.octane) : null },
        { label: 'Database', value: environment.laravel?.drivers?.database },
        { label: 'OPcache', value: environment.php?.op_cache != null ? bool(environment.php.op_cache) : null },
        { label: 'OPcache JIT', value: environment.php?.op_cache_jit },
        { label: 'Memory limit', value: environment.php?.memory_limit },
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
</script>
