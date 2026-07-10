<template>
    <div class="flex flex-col mt-3">
        <div class="flex font-bold font-mono text-sm text-white h-5">
            ⚡️ PHP Settings
        </div>

        <FactRow label="Server API">{{ php.php_server_api }}</FactRow>
        <FactRow label="Memory Limit">{{ php.memory_limit }}</FactRow>
        <FactRow label="OP Cache" :value-class="php.op_cache == '1' ? 'text-yellow-500' : 'text-red-500'">
            {{ php.op_cache == '1' ? 'ENABLED' : 'DISABLED' }}
        </FactRow>
    </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FactRow from '@/Components/FactRow.vue'

const php = computed(() => usePage().props.php);
</script>
