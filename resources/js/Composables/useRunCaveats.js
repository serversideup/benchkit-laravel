/**
 * Everything that changes how a run's numbers should be read, as data.
 *
 * The results page renders these and the submit flow gates on them, so a run
 * cannot be told one thing on screen and judged by another when it is offered
 * to the gallery.
 *
 * Each entry answers three things in order: what is off, why it matters, and
 * what to do about it. Naming the setting is the least useful part; someone
 * reading their first run does not yet know why OPcache matters.
 *
 * 'high' means the numbers are wrong. 'medium' means they are right but easy
 * to misread. 'note' means neither.
 */

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

/**
 * A run that measured something other than the host it names. The load
 * generator ran out of capacity, or the routes answered with errors — which
 * are cheaper to serve than real responses, so the throughput reads high
 * rather than low. Neither is a slow result; both are a different measurement,
 * and no amount of labelling makes them comparable.
 */
export const SUBMISSION_BLOCKERS = ['failed-requests', 'generator-bound'];

/**
 * A real measurement of a misconfigured application rather than of the host.
 * Every one of these is a setting the operator can change in a minute, and
 * almost nobody means to publish one.
 */
export const SUBMISSION_WARNINGS = ['debug', 'memory-database', 'unoptimized', 'opcache'];

/**
 * What the gallery should be told about this run before it accepts it.
 *
 * Deliberately not "is it clean". A self-test is the zero-setup default and a
 * run that never saturated is honest about its own ceiling; both fail the
 * clean check and both belong in the gallery, labelled. Only two things make a
 * run unpublishable, and they are the two that measured something else.
 */
/**
 * Read order: worst first, so the list answers "what do I fix" from the top
 * and the notes gather at the bottom where they belong.
 *
 * A blocker outranks every other issue because it says the run measured
 * something else entirely — next to that, a setting being wrong is a detail.
 * The sort is stable, so the order each tier is detected in is preserved; that
 * order is the sequence an operator would work through them in.
 */
const SEVERITY_RANK = { high: 1, medium: 2, note: 3 };

const rankOf = (caveat) => (SUBMISSION_BLOCKERS.includes(caveat.key) ? 0 : SEVERITY_RANK[caveat.severity] ?? 3);

export const submissionGate = (caveats) => ({
    blockers: caveats.filter((caveat) => SUBMISSION_BLOCKERS.includes(caveat.key)),
    warnings: caveats.filter((caveat) => SUBMISSION_WARNINGS.includes(caveat.key)),
});

/**
 * Cores this run could never have used. Only meaningful for a server that ties
 * a request to a worker for its duration — a worker-mode runtime multiplexes,
 * so the comparison does not hold there.
 */
const idleCoresFor = (environment, http) => {
    const workers = http?.workers;
    const cores = coresFor(environment);
    const perRequest = environment?.php?.runtime?.mode === 'process-per-request';

    if (!perRequest || !workers || !cores) {
        return 0;
    }

    return Math.max(0, cores - workers);
};

const coresFor = (environment) => Number.parseInt(String(environment?.server?.cpu_cores ?? ''), 10) || null;

export const runCaveats = ({ environment, http: httpInput } = {}) => {
    const found = [];
    const cores = coresFor(environment);
    const idleCores = idleCoresFor(environment, httpInput);
    const env = environment ?? {};
    const http = httpInput ?? {};
    const opcache = env.php?.op_cache;

    if (env.laravel?.environment?.debug_mode === true) {
        found.push({
            key: 'debug',
            severity: 'high',
            title: 'Debug mode was on',
            detail: 'Laravel built a stack trace on every request, which it never does in production.',
            fix: 'Set `APP_DEBUG=false` and run again.',
            command: 'APP_DEBUG=false',
        });
    }

    // Written for someone who has never heard of fsync, because that is who
    // reads this. The settings behind it stay in the Environment panel, where
    // they are config to look up rather than a sentence to read.
    if (MEMORY_FILESYSTEMS.includes(String(env.database?.filesystem ?? '').toLowerCase())) {
        found.push({
            key: 'memory-database',
            severity: 'high',
            title: 'The database ran in memory',
            detail: 'Writes never reached a disk, so create, update and delete are far faster than this host would manage.',
            fix: 'Point the database at disk-backed storage and run again.',
        });
    } else if (Object.values(env.database?.durability ?? {}).some((value) => ['off', '0'].includes(String(value).toLowerCase()))) {
        found.push({
            key: 'unsafe-writes',
            severity: 'medium',
            title: 'The database did not wait for writes to land',
            detail: 'A write counted as finished before the drive stored it, which lifts create, update and delete.',
            fix: 'Turn synchronous writes back on and run again.',
        });
    }

    // Config and route caches are files on disk, so the command line and the
    // web process agree about them — unlike OPcache, which each SAPI holds
    // separately. That makes this safe to read from the run's own environment.
    const laravelCache = env.laravel?.cache ?? {};
    const uncached = ['config', 'routes', 'events'].filter((key) => laravelCache[key] === false);

    if (uncached.length > 0 || String(env.php?.ini?.['opcache.validate_timestamps'] ?? '0') === '1') {
        found.push({
            key: 'unoptimized',
            severity: 'high',
            title: 'The app was not prepared for production',
            detail: 'Every request re-read the configuration and routes that a deployed app reads once at boot.',
            fix: 'Run `php artisan optimize` and run again.',
            command: 'php artisan optimize',
        });
    }

    if (opcache != null && String(opcache) !== '1') {
        found.push({
            key: 'opcache',
            severity: 'high',
            title: 'OPcache was off',
            detail: 'PHP recompiled the whole application from source on every request. Practically no production host runs this way.',
            fix: 'Set `opcache.enable=1` and run again.',
            command: 'opcache.enable=1',
        });
    }

    // Arithmetic, not a heuristic. In a closed loop concurrency = rate x
    // response time is an identity; a level where that did not hold had
    // connections sitting idle, which is the machine driving the load running
    // out of capacity rather than the one serving it.
    if (http.generator_bound === true) {
        const rtt = http.generator?.rtt_ms ?? null;

        found.push({
            key: 'generator-bound',
            severity: 'high',
            title: 'The load generator was the limit, not this server',
            detail: rtt
                ? `Connections sat idle waiting on the ${rtt}ms path, so these throughput figures describe the generator.`
                : 'Connections sat idle waiting on the generator rather than on this server, so these throughput figures describe the generator.',
            fix: 'Run again from a closer machine, or one with more headroom.',
        });
    }

    // Arithmetic again: a process-per-request server cannot keep more cores
    // busy than it has workers. The shipped pool is a fixed 20 regardless of
    // hardware, so every machine bigger than that measures a fraction of
    // itself unless the operator raised it.
    if (idleCores > 0) {
        found.push({
            key: 'undersized-pool',
            severity: 'medium',
            title: `Only ${http.workers} of ${cores} cores were usable`,
            detail: `PHP serves one request per worker, so ${idleCores} cores sat idle for the whole test and this result is below what the hardware can do.`,
            fix: `Set \`PHP_FPM_PM_MAX_CHILDREN=${cores}\`, restart, and run again.`,
            command: `PHP_FPM_PM_MAX_CHILDREN=${cores}`,
        });
    }

    // oha's success rate is transport-level, so a route answering 503 to
    // everything reports as a perfect run — and a faster one than a working
    // server, because an error is cheap to produce.
    const failing = Object.entries(http.routes ?? {})
        .filter(([, route]) => Object.keys(route?.throughput?.status_codes ?? {})
            .some((code) => Number(code) < 200 || Number(code) >= 300))
        .map(([key]) => ROUTE_LABELS[key] ?? key);

    if (failing.length > 0) {
        found.push({
            key: 'failed-requests',
            severity: 'high',
            title: 'Some requests failed',
            detail: `${listOf(failing)} answered with errors, and an error is far cheaper to serve than a real response.`,
            fix: 'Fix those routes and run again. Nothing here is comparable until you do.',
        });
    }

    // The single-connection level is the same request over the same network
    // with nothing queued, so it is what the path and the framework cost
    // before load is a factor.
    //
    // Measured against the idle *median*, not the idle tail. The tail of a
    // six-second window is one or two requests and moves wildly — the same
    // route measured an idle p95 of 18ms in one run and 93ms in the next,
    // which silently stopped this firing. The idle median sat at 12-13ms in
    // both, on both pool settings.
    const TAIL_GROWTH = 5;

    const strained = Object.entries(http.routes ?? {})
        .map(([key, route]) => ({
            key,
            idle: (route?.curve ?? []).find((point) => point.concurrency === 1)?.p50_ms ?? null,
            loaded: route?.latency?.p95_ms ?? null,
        }))
        .filter(({ idle, loaded }) => idle != null && loaded != null && loaded > idle * TAIL_GROWTH);

    if (strained.length > 0) {
        const worst = strained.reduce((a, b) => (b.loaded / b.idle > a.loaded / a.idle ? b : a));

        found.push({
            key: 'tail-under-load',
            severity: 'medium',
            title: 'Response times climb once this server is busy',
            detail: `${ROUTE_LABELS[worst.key] ?? worst.key} answers in ${Math.round(worst.idle)}ms alone, but at 70% of capacity the slowest 5% take ${Math.round(worst.loaded)}ms. That is a queue, seen from outside.`,
            // Deliberately not "keep the pool warm". That was measured on the
            // machine this text was written for and made the median three
            // times worse, because a resident pool needs memory that box did
            // not have. Comparing two runs is what this tool is for.
            fix: 'Change the worker count, run again, and compare the two.',
        });
    }

    if (http.mode === 'app-url') {
        found.push({
            key: 'target',
            severity: 'medium',
            title: 'Traffic went out through your public URL',
            detail: 'BenchKit could not reach the app directly, so every request also paid for a proxy and a round trip.',
            fix: 'Compare this only against other runs measured the same way.',
        });
    }

    // The strongest thing in the results, and the only one that is arithmetic
    // rather than measurement: a request on the I/O route holds a worker for
    // its whole simulated wait, so workers x 1000/io_ms is the most the pool
    // can serve however fast the machine is. The curve lands on that line.
    //
    // A note, not a warning: the chart draws the ceiling and the figure is
    // labelled with the concurrency it happened at, so nothing is hidden.
    if (http.pool_ceiling?.at_ceiling === true) {
        const ceiling = http.pool_ceiling;

        found.push({
            key: 'pool-ceiling',
            severity: 'note',
            title: `The worker pool caps the I/O route at ~${Math.round(ceiling.predicted_rps).toLocaleString()} req/s`,
            // The claim is about the rate, which is arithmetic, not about the
            // concurrency the curve happened to bend at.
            detail: `Each request there holds a worker for ${ceiling.io_ms}ms, so ${ceiling.workers} workers cannot beat that however fast the machine is. It flattened at ${Math.round(ceiling.observed_rps).toLocaleString()}, right on the line.`,
            fix: 'Raise the worker count to move the line.',
        });
    }

    // The gap that had no detector at all. A fixed connection count could not
    // tell "this is the maximum" from "this is as hard as we pushed", so a
    // large host quietly reported a fraction of itself as a flat number.
    const unsaturated = Object.entries(http.routes ?? {})
        .filter(([, route]) => route?.throughput?.saturated === false)
        .map(([key]) => ROUTE_LABELS[key] ?? key);

    if (unsaturated.length > 0) {
        const needed = http.required_concurrency ?? null;
        const measured = Math.max(0, ...Object.values(http.routes ?? {})
            .flatMap((route) => (route?.curve ?? []).map((point) => point.concurrency ?? 0)));
        const rtt = http.generator?.rtt_ms ?? null;
        const distant = needed && measured && needed > measured && (http.generator?.mode ?? 'self') === 'external';

        found.push({
            key: 'not-saturated',
            severity: 'medium',
            title: distant
                ? 'The generator is too far away to find the limit'
                : 'The limit was never reached',
            detail: distant
                ? `This server answers faster than the ${rtt}ms round trip to the generator, so saturating it would take about ${needed.toLocaleString()} connections and BenchKit could offer ${measured.toLocaleString()}. Throughput on ${listOf(unsaturated)} is a floor.`
                : `Throughput on ${listOf(unsaturated)} was still climbing at the highest concurrency BenchKit measures, so read those figures as "at least this much".`,
            // The load sizes itself, so there is no setting left to turn up.
            // When distance is the cause the arithmetic gives the remedy;
            // otherwise there honestly isn't one.
            fix: distant
                ? 'Run the generator in the same datacenter, or self-test for a number the network cannot bound.'
                : null,
        });
    }

    // Context, not a defect: the self-test is the zero-setup default and the
    // right instrument for comparing configurations on one machine.
    if (Object.keys(http.routes ?? {}).length > 0 && (http.generator?.mode ?? 'self') === 'self') {
        found.push({
            key: 'self-test',
            severity: 'note',
            title: 'This server generated its own load',
            detail: 'The generator shared CPU with the server it was measuring, so absolute throughput reads lower than it really is.',
            fix: 'Run an external load test from a second machine for the absolute number.',
        });
    }

    // A run assembled without the HTTP stage reports the CLI process's PHP
    // configuration, because there was no web server to ask.
    if (env.php_environment_source === 'cli') {
        found.push({
            key: 'environment-source',
            severity: 'note',
            title: 'These PHP settings are the command line\'s',
            detail: 'This run skipped the web server test, and PHP keeps a separate set of settings for the web.',
            fix: 'Include the web server test to see what actually served the requests.',
        });
    }

    return found.sort((a, b) => rankOf(a) - rankOf(b));
};
