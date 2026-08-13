<template>
    <div v-if="caveats.length" class="flex flex-col gap-2.5">
        <div v-for="caveat in caveats" :key="caveat.key"
            class="flex gap-3 rounded-xl border p-4" :class="TONES[caveat.severity].box">
            <!-- The mark is what separates a defect from a note at a glance,
                 which the boxes alone could not do — a wash of colour behind a
                 paragraph reads the same whatever it says. -->
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="mt-px size-4 shrink-0" :class="TONES[caveat.severity].mark">
                <path d="M10 7.5v3M10 13.5h.007M8.57 3.02 1.6 15a1.67 1.67 0 0 0 1.43 2.5h13.94A1.67 1.67 0 0 0 18.4 15L11.43 3.02a1.67 1.67 0 0 0-2.86 0Z"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <div class="min-w-0">
                <p class="text-sm font-medium" :class="TONES[caveat.severity].title">{{ caveat.title }}</p>
                <!-- Held to a readable measure. Full-width prose across the
                     page ran to well over a hundred characters a line, which is
                     most of why these felt like walls rather than warnings. -->
                <p class="mt-1 max-w-[62ch] text-sm text-[#94979C] leading-relaxed">{{ caveat.detail }}</p>
            </div>
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

/** Filesystems that are memory pretending to be storage. */
const MEMORY_FILESYSTEMS = ['tmpfs', 'ramfs', 'memory'];

// Red means the numbers are wrong. Amber means they are right and easy to
// misread. Grey means neither — context, styled so a healthy run does not look
// like a list of failures.
const TONES = {
    high: { box: 'border-[#F97066]/25 bg-[#F97066]/[0.04]', mark: 'text-[#F97066]', title: 'text-[#F97066]' },
    medium: { box: 'border-[#F79009]/25 bg-[#F79009]/[0.04]', mark: 'text-[#F79009]', title: 'text-[#F79009]' },
    note: { box: 'border-[#22262F]', mark: 'text-[#61656C]', title: 'text-[#CECFD2]' },
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
            detail: 'Laravel built a stack trace on every request, which it never does in production. Set APP_DEBUG=false and run again.',
        });
    }

    // Written for someone who has never heard of fsync, because that is who
    // reads this. The settings behind it (journal_mode, synchronous, and the
    // filesystem the database sits on) stay in the Environment panel, where
    // they are config to look up rather than a sentence to read.
    if (MEMORY_FILESYSTEMS.includes(String(environment.database?.filesystem ?? '').toLowerCase())) {
        found.push({
            key: 'memory-database',
            severity: 'high',
            title: 'These write speeds came from a database in memory',
            detail: 'It was stored in RAM rather than on a disk, so Create, Update, and Delete are far faster here than this host would manage in production.',
        });
    } else if (Object.values(environment.database?.durability ?? {}).some((value) => ['off', '0'].includes(String(value).toLowerCase()))) {
        found.push({
            key: 'unsafe-writes',
            severity: 'medium',
            title: 'The database was not waiting for writes to reach the disk',
            detail: 'It was set to report a write as finished before the drive had actually stored it — faster, but recent data is lost in a crash. The Create, Update, and Delete figures are higher than a normally configured database would produce.',
        });
    }

    if (opcache != null && String(opcache) !== '1') {
        found.push({
            key: 'opcache',
            severity: 'high',
            title: 'This server is much faster than these numbers say',
            detail: 'PHP recompiled the whole application from source on every request. Practically no production host runs this way.',
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
