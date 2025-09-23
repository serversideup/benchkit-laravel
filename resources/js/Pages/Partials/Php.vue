<template>
    <Deferred data="php">
        <template #fallback>
            <div>Loading...</div>
        </template>

        <div class="flex flex-col mt-3">
            <div class="flex font-bold font-mono text-sm text-white h-5">
                ⚡️ PHP Settings
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Server API</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ php.php_server_api }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Memory Limit</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ php.memory_limit }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>OP Cache</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div :class="{ 'text-yellow-500': php.op_cache == '1', 'text-red-500': php.op_cache == '0' }">
                    {{ php.op_cache == '1' ? 'ENABLED' : 'DISABLED' }}
                </div>
            </div>
        </div>
    </Deferred>
</template>

<script setup>
import { Deferred, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const php = computed(() => usePage().props.php);
</script>