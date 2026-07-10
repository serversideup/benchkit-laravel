<template>
    <div class="flex flex-col">
        <div class="flex font-bold font-mono text-sm text-white h-5">
            🤖 Server
        </div>

        <FactRow label="CPU Model">{{ server.cpu_model }}</FactRow>
        <FactRow label="CPU Cores">{{ server.cpu_cores }}</FactRow>
        <FactRow label="CPU Frequency">{{ server.cpu_frequency }}</FactRow>
        <FactRow label="RAM">{{ server.ram }}</FactRow>
        <FactRow label="Operating System">{{ server.os }}</FactRow>
    </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FactRow from '@/Components/FactRow.vue'

const server = computed(() => usePage().props.server);
</script>
