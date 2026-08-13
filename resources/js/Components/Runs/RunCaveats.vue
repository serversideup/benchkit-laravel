<template>
    <div v-if="caveats.length" class="flex flex-col gap-3">
        <div v-for="caveat in caveats" :key="caveat.key"
            class="rounded-lg border p-4" :class="TONES[caveat.severity].box">
            <p class="text-sm font-medium" :class="TONES[caveat.severity].title">
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

// Red is for numbers that are wrong, amber for numbers that are right and easy
// to misread, and grey for context. Everything used to be red or amber, which
// made a healthy run look like it had two problems.
const cores = computed(() => Number.parseInt(String(props.environment?.server?.cpu_cores ?? ''), 10) || null);

/**
 * Cores this run could never have used. Only meaningful for a server that ties
 * a request to a worker for its duration — a worker-mode runtime multiplexes,
 * so the comparison does not hold there.
 */
const idleCores = computed(() => {
    const workers = props.http?.workers;
    const perRequest = props.environment?.php?.runtime?.mode === 'process-per-request';

    if (!perRequest || !workers || !cores.value) {
        return 0;
    }

    return Math.max(0, cores.value - workers);
});

const TONES = {
    high: { box: 'border-[#F97066]/40 bg-[#F97066]/5', title: 'text-[#F97066]' },
    medium: { box: 'border-[#F79009]/30 bg-[#F79009]/5', title: 'text-[#F79009]' },
    note: { box: 'border-[#22262F] bg-[#13161B]', title: 'text-[#CECFD2]' },
};

/**
 * Each of these answers the same three questions, in this order: what is wrong
 * with the numbers, what to do about it, and whether the result can still be
 * compared with others. Naming the setting is the least useful part — someone
 * reading their first BenchKit run does not yet know why OPcache matters, and
 * "OPcache was disabled" tells them nothing they can act on.
 *
 * 'high' means the numbers are wrong. 'medium' means they are right but easy to
 * misread. 'note' means neither — it is context, and it is styled quietly so a
 * result page does not look like a list of failures when nothing failed.
 */
const caveats = computed(() => {
    const found = [];
    const environment = props.environment ?? {};
    const http = props.http ?? {};
    const opcache = environment.php?.op_cache;

    if (environment.laravel?.environment?.debug_mode === true) {
        found.push({
            key: 'debug',
            severity: 'high',
            title: 'This server is faster than these numbers say',
            detail: 'The app was running in debug mode, so Laravel built a stack trace on every single request — something it never does in production. Set APP_DEBUG=false and run again to see what this host really does. Until then, don\'t compare this against other results.',
        });
    }

    if (opcache != null && String(opcache) !== '1') {
        found.push({
            key: 'opcache',
            severity: 'high',
            title: 'This server is much faster than these numbers say',
            detail: 'OPcache was off, so PHP recompiled the whole application from source on every request. Practically no production host runs this way. Turn OPcache on and run again — this result can\'t be compared with the rest of the gallery.',
        });
    }

    // Arithmetic, not a heuristic: a process-per-request server cannot keep
    // more cores busy than it has workers. The shipped pool size is a fixed 20
    // regardless of hardware, so every machine bigger than that measures a
    // fraction of itself unless the operator raised it.
    if (idleCores.value > 0) {
        found.push({
            key: 'undersized-pool',
            severity: 'medium',
            title: `This machine has ${cores.value} cores and only ${http.workers} were usable`,
            detail: `PHP handles one request per worker, so with ${http.workers} workers at most ${http.workers} cores can be busy at once — the other ${idleCores.value} sat idle for the whole test. This result is well below what this hardware can do. Restart with PHP_FPM_PM_MAX_CHILDREN set to at least the core count and run again.`,
        });
    }

    if (http.oversubscribed) {
        found.push({
            key: 'queueing',
            severity: 'note',
            title: 'The response times below are mostly queueing',
            detail: `This test deliberately holds ${http.connections} requests open at once against a server that works on ${http.workers} at a time, to find the point where it maxes out. The rest wait in line, and that wait is counted in the times below — so they describe a server under a stampede, not what one visitor would see. The req/s figures are the ones to read here.`,
        });
    }

    if (http.mode === 'app-url') {
        found.push({
            key: 'target',
            severity: 'medium',
            title: 'Some of this measures your network, not your server',
            detail: 'BenchKit could not reach the app directly and went out through your public URL instead, so every request also paid for a proxy and a round trip. Compare this only against other runs measured the same way.',
        });
    }

    // A run assembled without the HTTP stage reports the CLI process's PHP
    // configuration, because there was no web server to ask.
    if (environment.php_environment_source === 'cli') {
        found.push({
            key: 'environment-source',
            severity: 'note',
            title: 'The PHP settings shown are the command line\'s',
            detail: 'This run skipped the web server test, so there was no web server to ask. The OPcache, JIT, and memory limit below come from the command-line PHP that assembled these results — PHP keeps a separate set of those for the web, and they may differ.',
        });
    }

    return found;
});
</script>
