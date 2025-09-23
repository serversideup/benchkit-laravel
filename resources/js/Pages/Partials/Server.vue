<template>
    <Deferred data="server">
        <template #fallback>
            <div>Loading...</div>
        </template>

        <div class="flex flex-col">
            <div class="flex font-bold font-mono text-sm text-white h-5">
                🤖 Server
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>CPU Model</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ server.cpu_model }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>CPU Cores</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ server.cpu_cores }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>CPU Frequency</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ server.cpu_frequency }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>RAM</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ server.ram }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Operating System</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ server.os }}
                </div>
            </div>
        </div>
    </Deferred>
</template>

<script setup>
import { Deferred, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const server = computed(() => usePage().props.server);
</script>