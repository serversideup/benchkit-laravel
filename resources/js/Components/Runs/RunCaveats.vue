<template>
    <div v-if="caveats.length" class="flex flex-col gap-2.5">
        <div v-for="caveat in caveats" :key="caveat.key"
            class="flex gap-3 rounded-xl border p-4" :class="TONES[caveat.severity].box">
            <!-- The mark is what separates a defect from a note at a glance,
                 which the boxes alone could not do — a wash of color behind a
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

/** What each route is called in a sentence, rather than by its key. */
const ROUTE_LABELS = {
    static: 'Static',
    json: 'JSON API',
    db_read: 'DB read',
    io: 'I/O-bound',
};

/** "A", "A and B", "A, B and C" — a list a person would read aloud. */
const listOf = (items) => items.length <= 1
    ? (items[0] ?? '')
    : `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}`;

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

    // Config and route caches are files on disk, so the command line and the
    // web process agree about them — unlike OPcache, which each SAPI holds
    // separately. That makes this safe to read from the run's own environment.
    const laravelCache = environment.laravel?.cache ?? {};
    const uncached = ['config', 'routes', 'events']
        .filter((key) => laravelCache[key] === false);

    if (uncached.length > 0 || String(environment.php?.ini?.['opcache.validate_timestamps'] ?? '0') === '1') {
        found.push({
            key: 'unoptimized',
            severity: 'high',
            title: 'This server is faster than these numbers say',
            detail: 'The application was not prepared the way it would be for production, so every request re-read configuration and routes that a deployed app reads once. Run `php artisan optimize` and try again — on the official image this happens automatically, so a run without it is usually one started from source.',
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

    // Arithmetic, not a heuristic. In a closed loop each connection holds one
    // request at a time, so concurrency = rate x response time is an identity;
    // a level where that did not hold had connections sitting idle, which is
    // the machine driving the load running out of capacity rather than the one
    // serving it.
    if (http.generator_bound === true) {
        const rtt = http.generator?.rtt_ms ?? null;

        found.push({
            key: 'generator-bound',
            severity: 'high',
            title: 'The load generator was the limit, not this server',
            detail: rtt
                ? `The machine sending the traffic could not keep its connections busy, so these throughput figures describe that machine and the ${rtt}ms path between it and this server rather than the server itself. Run again from somewhere closer, or from a machine with more headroom.`
                : 'The machine sending the traffic could not keep its connections busy — some sat idle waiting on the generator rather than on this server. These throughput figures describe the generator. Run again from somewhere closer, or from a machine with more headroom.',
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

    // oha's own success rate is transport-level, so a route answering 503 to
    // everything reports as a perfect run — and a faster one than a working
    // server, because an error is cheap to produce. The status codes are the
    // only place that shows.
    const failing = Object.entries(http.routes ?? {})
        .filter(([, route]) => Object.keys(route?.throughput?.status_codes ?? {})
            .some((code) => Number(code) < 200 || Number(code) >= 300))
        .map(([key]) => ROUTE_LABELS[key] ?? key);

    if (failing.length > 0) {
        found.push({
            key: 'failed-requests',
            severity: 'high',
            title: 'Some of these requests failed',
            detail: `${listOf(failing)} answered with errors rather than results. A failing request is far cheaper to serve than a real one, so those throughput figures are higher than a working server would produce, not lower. This run is not comparable with anything.`,
        });
    }

    // The single-connection level is the same request over the same network
    // with nothing queued, so it is what the path and the framework cost
    // before load is a factor. A loaded tail many times larger than that
    // appeared because of the load.
    //
    // Measured against the idle *median*, not the idle tail. The tail of a
    // six-second window is one or two requests and moves wildly — the same
    // route measured an idle p95 of 18ms in one run and 93ms in the next,
    // which silently stopped this firing. The idle median sat at 12-13ms in
    // both, on both pool settings.
    const TAIL_GROWTH = 5;

    const strained = Object.entries(http.routes ?? {})
        .map(([key, route]) => {
            const idle = (route?.curve ?? []).find((point) => point.concurrency === 1)?.p50_ms ?? null;
            const loaded = route?.latency?.p95_ms ?? null;

            return { key, idle, loaded };
        })
        .filter(({ idle, loaded }) => idle != null && loaded != null && loaded > idle * TAIL_GROWTH);

    if (strained.length > 0) {
        const worst = strained.reduce((a, b) => (b.loaded / b.idle > a.loaded / a.idle ? b : a));

        found.push({
            key: 'tail-under-load',
            severity: 'medium',
            title: 'Response times hold up until this server gets busy',
            // Deliberately no instruction. The obvious one — keep the worker
            // pool warm instead of starting it on demand — was measured on the
            // machine this text was written for and made the median three
            // times worse, because a resident pool needs memory that box did
            // not have. What helps depends on facts BenchKit does not check,
            // and confident wrong advice is worse than none. Describing what
            // happened and pointing at the comparison is the honest version,
            // and comparing two runs is what this tool is for.
            detail: `A single request on ${ROUTE_LABELS[worst.key] ?? worst.key} comes back in about ${Math.round(worst.idle)}ms. At around 70% of what this machine can serve, the slowest 5% take ${Math.round(worst.loaded)}ms — over the same network, so the difference is this server rather than the path. That is what a queue looks like from outside. Worker count and how the pool is started both change it, in directions that depend on the memory and cores you have: change one, run again, and compare the two.`,
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

    // The strongest thing in the results, and the only one that is arithmetic
    // rather than measurement: a request on the I/O route holds a worker for
    // its whole simulated wait, so workers x 1000/io_ms is the most the pool
    // can serve however fast the machine is. The curve is drawn against that
    // predicted line and lands on it.
    //
    // Downgraded from a warning. Under a fixed connection count, hitting the
    // ceiling silently made the number about the pool. Now the chart shows the
    // ceiling and the figure is labelled with the concurrency it happened at,
    // so this is context rather than a fault — and a run where everything is
    // understood should not render an amber box.
    if (http.pool_ceiling?.at_ceiling === true) {
        const ceiling = http.pool_ceiling;

        found.push({
            key: 'pool-ceiling',
            severity: 'note',
            title: `The worker pool is what caps the I/O route at ${Math.round(ceiling.predicted_rps).toLocaleString()} req/s`,
            // The claim is about the *rate*, which is arithmetic, and not about
            // the concurrency the curve happened to bend at. This used to say
            // "which is your worker count" about whichever level the ladder
            // landed on — 36 against a pool of 20 on the run that caught it.
            detail: `Each request on that route holds a worker for about ${ceiling.io_ms}ms, so ${ceiling.workers} workers can serve at most ~${Math.round(ceiling.predicted_rps).toLocaleString()} a second however fast the machine is — and it flattened at ${Math.round(ceiling.observed_rps).toLocaleString()}, right on that line. That is the pool being measured rather than the machine. Raise the worker count to move the line.`,
        });
    }

    // The gap that had no detector at all. A fixed connection count could not
    // tell "this is the maximum" from "this is as hard as we pushed", so a
    // large host quietly reported a fraction of itself as a flat number.
    const unsaturated = Object.entries(http.routes ?? {})
        .filter(([, route]) => route?.throughput?.saturated === false)
        .map(([key]) => ROUTE_LABELS[key] ?? key);

    if (unsaturated.length > 0) {
        // A run that cannot reach a maximum has to say why and what to do
        // about it. Saying only "this is a floor" is a dead end: the load
        // sizes itself now, so there is no setting left for a reader to turn
        // up. When the generator's distance is the cause, the arithmetic gives
        // both the reason and the remedy.
        const needed = http.required_concurrency ?? null;
        const measured = Math.max(0, ...Object.values(http.routes ?? {})
            .flatMap((route) => (route?.curve ?? []).map((point) => point.concurrency ?? 0)));
        const rtt = http.generator?.rtt_ms ?? null;
        const distant = needed && measured && needed > measured && (http.generator?.mode ?? 'self') === 'external';

        found.push({
            key: 'not-saturated',
            severity: 'medium',
            title: distant
                ? 'The load generator is too far away to find this server\'s limit'
                : 'This machine was still getting faster when the test ran out of room',
            detail: distant
                ? `This server answers far faster than the ${rtt}ms round trip to the machine sending the traffic, so almost every connection spends its life in transit rather than at the server. Reaching this pool from there would take about ${needed.toLocaleString()} connections at once, and BenchKit offered ${measured.toLocaleString()}. Throughput on ${listOf(unsaturated)} is a floor. Run the generator from the same datacenter, or run a self-test for a number that is not bounded by the network.`
                : `Throughput on ${listOf(unsaturated)} was still climbing at the highest concurrency BenchKit measures. Those figures are the most it could ask for, not the most this machine can serve — read them as "at least this much".`,
        });
    }

    // Context, not a defect: the self-test is the zero-setup default and the
    // right instrument for comparing configurations on one machine.
    if (Object.keys(http.routes ?? {}).length > 0 && (http.generator?.mode ?? 'self') === 'self') {
        found.push({
            key: 'self-test',
            severity: 'note',
            title: 'This server generated its own load',
            detail: 'The load generator ran on the machine it was testing and shared the CPU with the server. That keeps the test honest for comparing configurations on this same machine, but absolute throughput reads lower than a dedicated generator would measure. For the honest absolute number, run an external load test from a second machine.',
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
