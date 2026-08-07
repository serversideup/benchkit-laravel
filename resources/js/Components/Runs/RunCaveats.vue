<template>
    <div v-if="caveats.length" class="flex flex-col gap-3">
        <div v-for="caveat in caveats" :key="caveat.key"
            class="rounded-lg border p-4"
            :class="caveat.severity === 'high' ? 'border-[#F97066]/40 bg-[#F97066]/5' : 'border-[#F79009]/30 bg-[#F79009]/5'">
            <p class="text-sm font-medium" :class="caveat.severity === 'high' ? 'text-[#F97066]' : 'text-[#F79009]'">
                {{ caveat.title }}
            </p>
            <p class="mt-1 text-xs text-[#94979C] leading-relaxed">{{ caveat.detail }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * Conditions that change how the whole run should be read, surfaced above the
 * numbers rather than below them. Everything here is also recorded in the
 * Environment panel; the point of repeating it at the top is that a run gets
 * screenshotted and quoted from the top.
 */
const props = defineProps({
    environment: {
        type: Object,
        default: null,
    },
    http: {
        type: Object,
        default: null,
    },
});

const caveats = computed(() => {
    const found = [];
    const opcache = props.environment?.php?.op_cache;

    if (opcache != null && String(opcache) !== '1') {
        found.push({
            key: 'opcache',
            severity: 'high',
            title: 'OPcache was disabled for this run',
            detail: 'PHP recompiled every request from source. Throughput here is far below what this hardware does in production, and these numbers are not comparable with runs that had OPcache enabled.',
        });
    }

    if (props.http?.pool_limited) {
        found.push({
            key: 'pool',
            severity: 'medium',
            title: 'The load test exceeded the PHP-FPM worker pool',
            detail: `This run held ${props.http.connections} connections open against ${props.http.fpm_max_children} workers. Throughput was bounded by pm.max_children rather than by the application, so it is not a clean framework comparison.`,
        });
    }

    if (props.http?.mode === 'app-url') {
        found.push({
            key: 'target',
            severity: 'medium',
            title: 'Measured through APP_URL rather than loopback',
            detail: 'The load test went through a proxy and the network, which adds overhead that a loopback run does not have. Compare only against other APP_URL runs.',
        });
    }

    return found;
});
</script>
