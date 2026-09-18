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
 * The conditions themselves are shared with the gallery; only the wording is
 * here. 'high' means the numbers are wrong, 'medium' that they are right and
 * easy to misread, 'note' neither.
 */

import {
    brokenRoutes,
    debugMode,
    failingRoutes,
    generatorBound,
    memoryDatabase,
    notOptimized,
    opcacheOff,
    pathJitterMs,
    selfTested,
    tailUnderLoad,
    undersizedPool,
    unsafeWrites,
    unsaturatedCause,
    unsaturatedRoutes,
} from '@shared/run/conditions.mjs';

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

const labelled = (keys) => listOf(keys.map((key) => ROUTE_LABELS[key] ?? key));

/** Route lists read as a subject, so the verb has to agree with how many. */
const were = (keys) => (keys.length === 1 ? 'was' : 'were');

const rounded = (value) => Math.round(value).toLocaleString();

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
 * Read order: worst first, so the list answers "what do I fix" from the top
 * and the notes gather at the bottom where they belong.
 *
 * A blocker outranks every other issue because it says the run measured
 * something else entirely — next to that, a setting being wrong is a detail.
 * The sort is stable, so each tier keeps the order it was detected in, which
 * is the sequence an operator would work through them in.
 */
const SEVERITY_RANK = { high: 1, medium: 2, note: 3 };

const rankOf = (caveat) => (SUBMISSION_BLOCKERS.includes(caveat.key) ? 0 : SEVERITY_RANK[caveat.severity] ?? 3);

/**
 * What the gallery should be told about this run before it accepts it.
 *
 * Not "is it clean". A self-test is the zero-setup default and a run that never
 * saturated is honest about its own ceiling; both fail the clean check and both
 * belong in the gallery, labelled. Only two things make a run unpublishable,
 * and they are the two that measured something else.
 */
export const submissionGate = (caveats) => ({
    blockers: caveats.filter((caveat) => SUBMISSION_BLOCKERS.includes(caveat.key)),
    warnings: caveats.filter((caveat) => SUBMISSION_WARNINGS.includes(caveat.key)),
});

/**
 * How a run that never found its limit should be explained.
 *
 * Deliberately quotes no projected connection count. In a closed loop
 * concurrency = rate x response time is an identity, so any figure derived from
 * the measurement is one the run already offered; a number for what it would
 * have taken needs a capacity estimate the run does not have.
 */
const unsaturatedCaveat = (http, routes) => {
    const reach = http.reach ?? {};
    const named = labelled(routes);

    switch (unsaturatedCause(http)) {
        case 'distant':
            return {
                title: 'The generator is too far away to find the limit',
                detail: `Even under load, ${reach.transport_rtt_ms}ms of each ${reach.loaded_ms ?? reach.idle_ms}ms request was the trip to the generator rather than the server. One connection can carry ${rounded(reach.rps_per_connection)} requests a second, so the ${rounded(reach.connections)} BenchKit can open cap the offered rate at ${rounded(reach.offered_rps_ceiling)}. ${named} topped out there, so those figures are a floor set by the path.`,
                fix: 'Run the generator in the same datacenter, or self-test for a number the network cannot bound.',
            };
        case 'descriptors':
            return {
                title: 'The generator ran out of connections',
                detail: `Its descriptor limit stopped the sweep at ${rounded(reach.connections)} connections, and ${named} ${were(routes)} still climbing there.`,
                fix: 'Raise the generator\'s open file limit with `ulimit -n 65536`, or run it from a machine with a higher hard limit.',
                command: 'ulimit -n 65536',
            };
        case 'idle':
            return {
                title: 'The generator could not keep its connections busy',
                detail: `About ${rounded(reach.busy_connections)} of ${rounded(reach.connections)} connections were carrying a request at the peak, so ${named} measured the machine driving the load rather than this one.`,
                fix: 'Run again from a machine with more headroom.',
            };
        default:
            return {
                title: 'The limit was never reached',
                // reach describes one route — the quickest — so its latencies
                // only belong in this sentence when that is a route the
                // sentence is about.
                detail: reach.capped_by === 'benchkit' && reach.loaded_ms != null && routes.includes(reach.route)
                    ? `${named} ${were(routes)} still climbing at ${rounded(reach.connections)} connections, the most the machine driving the load could hold open, rather than anything about this server. Each request took ${reach.loaded_ms}ms there against ${reach.idle_ms}ms idle, so the queue is this machine's and the figures are a floor.`
                    : `Throughput on ${named} was still climbing at the most concurrency the load generator could offer, so read those figures as "at least this much".`,
                fix: null,
            };
    }
};

/** Where the Laravel log lives on the server, for a fix that sends someone to it. */
const LARAVEL_LOG = '`storage/logs/laravel.log`';

/**
 * How a route that broke should be explained, from what it answered there.
 *
 * The number alone is a symptom. The same "stopped at 199 connections" is a
 * database refusing connections, a front end giving up on PHP, or a level the
 * generator never measured, and each is a different repair — so the sentence
 * is built from the answer, and only falls back to the symptom for runs that
 * did not keep it.
 */
const brokenCaveat = (http, env, { key, at, answer }) => {
    const label = ROUTE_LABELS[key] ?? key;
    const title = `${label} broke at ${rounded(at)} connections`;
    const frontEnd = env.php?.runtime?.front_end ?? 'the front end';

    if (! answer) {
        return {
            title,
            detail: 'It was still gaining throughput when requests began failing, so its figure is what it reached before that rather than what this server can serve. This run did not keep what the route answered there.',
            fix: 'Find what the route depends on that gives out at that level, raise it, then run again.',
        };
    }

    // The level below is the last one that served every request: the breaking
    // point is by definition the lowest level that did not.
    const held = (http.routes?.[key]?.curve ?? [])
        .map((point) => point.concurrency)
        .filter((concurrency) => concurrency < at)
        .sort((a, b) => b - a)[0];
    const served = held ? `It served every request at ${rounded(held)} connections. ` : '';
    const share = `${Math.round(answer.share * 100)}%`;

    if (answer.kind === 'unmeasured') {
        return {
            title,
            detail: `${served}The generator could not measure ${rounded(at)}: ${answer.failure}.`,
            fix: 'Run again.',
        };
    }

    if (answer.kind === 'transport') {
        return {
            title,
            detail: `${served}At ${rounded(at)}, ${share} of requests failed before any answer came back: "${answer.error}".`,
            fix: `Something between the generator and PHP ran out at that level, and ${frontEnd}'s error log usually names it. Fix that, then run again.`,
        };
    }

    const { code } = answer;
    const meaning = code === 503 && key === 'db_read' ? ', which is the answer this route gives when its database query fails'
        : code === 502 || code === 504 ? `, which is ${frontEnd} giving up on PHP`
            : code === 500 ? ', an error PHP did not catch'
                : '';
    const fix = (code === 503 && key === 'db_read') || code === 500 ? `The error is logged in ${LARAVEL_LOG} on this server. Fix what it names, then run again.`
        : code === 502 || code === 504 ? `${frontEnd}'s error log names what it was waiting on. Fix that, then run again.`
            : `Fix whatever answers ${code} at that level, then run again.`;

    return {
        title,
        detail: `${served}At ${rounded(at)}, ${share} of requests came back ${code}${meaning}.`,
        fix,
    };
};

export const runCaveats = ({ environment, http: httpInput } = {}) => {
    const found = [];
    const env = environment ?? {};
    const http = httpInput ?? {};

    if (debugMode(env)) {
        found.push({
            key: 'debug',
            severity: 'high',
            title: 'Debug mode was on',
            detail: 'Laravel built a stack trace on every request, which it never does in production.',
            fix: 'Set `APP_DEBUG=false` and run again.',
            command: 'APP_DEBUG=false',
        });
    }

    // Written for someone who has not met fsync. The settings behind it stay in
    // the Environment panel, where they are config to look up rather than a
    // sentence to read.
    if (memoryDatabase(env)) {
        found.push({
            key: 'memory-database',
            severity: 'high',
            title: 'The database ran in memory',
            detail: 'Writes never reached a disk, so create, update and delete are far faster than this host would manage.',
            fix: 'Point the database at disk-backed storage and run again.',
        });
    } else if (unsafeWrites(env)) {
        found.push({
            key: 'unsafe-writes',
            severity: 'medium',
            title: 'The database did not wait for writes to land',
            detail: 'A write counted as finished before the drive stored it, which lifts create, update and delete.',
            fix: 'Turn synchronous writes back on and run again.',
        });
    }

    if (notOptimized(env)) {
        found.push({
            key: 'unoptimized',
            severity: 'high',
            title: 'The app was not prepared for production',
            detail: 'Every request re-read the configuration and routes that a deployed app reads once at boot.',
            fix: 'Run `php artisan optimize` and run again.',
            command: 'php artisan optimize',
        });
    }

    if (opcacheOff(env)) {
        found.push({
            key: 'opcache',
            severity: 'high',
            title: 'OPcache was off',
            detail: 'PHP recompiled the whole application from source on every request. Practically no production host runs this way.',
            fix: 'Set `opcache.enable=1` and run again.',
            command: 'opcache.enable=1',
        });
    }

    // In a closed loop concurrency = rate x response time is an identity; a
    // level where that did not hold had connections sitting idle, which is the
    // machine driving the load running out of capacity rather than this one.
    if (generatorBound(http)) {
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

    // A process-per-request server cannot keep more cores busy than it has
    // workers, and the shipped pool does not scale with the hardware.
    const pool = undersizedPool(env, http);

    if (pool) {
        found.push({
            key: 'undersized-pool',
            severity: 'medium',
            title: `Only ${pool.workers} of ${pool.cores} cores were usable`,
            detail: `PHP serves one request per worker, so ${pool.idleCores} cores sat idle for the whole test and this result is below what the hardware can do.`
                + (pool.memoryBound
                    ? ` A request spends much of its life waiting rather than computing, so the pool is bounded by memory rather than by cores: this machine has room for about ${pool.suggested.toLocaleString()} workers.`
                    : ''),
            fix: `Raise \`PHP_FPM_PM_MAX_CHILDREN\` to ${pool.suggested.toLocaleString()}, restart, and run again.`,
            command: `PHP_FPM_PM_MAX_CHILDREN=${pool.suggested}`,
        });
    }

    // oha's success rate is transport-level, so a route answering 503 to
    // everything reports as a perfect run — and a faster one than a working
    // server, because an error is cheap to produce.
    const failing = failingRoutes(http);

    if (failing.length > 0) {
        found.push({
            key: 'failed-requests',
            severity: 'high',
            title: 'Some requests failed',
            detail: `${labelled(failing)} answered with errors, and an error is far cheaper to serve than a real response.`,
            fix: 'Fix those routes and run again. Nothing here is comparable until you do.',
        });
    }

    // The single-connection level is the same request over the same network
    // with nothing queued, so it is what the path and the framework cost before
    // load is a factor.
    const strained = tailUnderLoad(http);

    if (strained.length > 0) {
        const worst = strained[0];
        const jitter = pathJitterMs(http);
        const jittery = jitter !== null && jitter > worst.loaded - worst.idle;

        found.push({
            key: 'tail-under-load',
            severity: 'medium',
            title: 'Response times climb once this server is busy',
            detail: `${ROUTE_LABELS[worst.key] ?? worst.key} answers in ${Math.round(worst.idle)}ms alone, but under load the slowest 5% take ${Math.round(worst.loaded)}ms. That is a queue, seen from outside.`
                + (jittery ? ` The path to the generator wobbled by ${Math.round(jitter)}ms on an idle server, which is enough to account for this on its own.` : ''),
            // Not "keep the pool warm": a resident pool needs memory a small box
            // does not have, and comparing two runs is what this tool is for.
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

    // Arithmetic rather than measurement: a request on the I/O route holds a
    // worker for its whole simulated wait, so the pool bounds it however fast
    // the machine is. A note, because the chart draws the ceiling and the
    // figure is labelled with the concurrency it happened at.
    if (http.pool_ceiling?.at_ceiling === true) {
        const ceiling = http.pool_ceiling;

        found.push({
            key: 'pool-ceiling',
            severity: 'note',
            title: `The worker pool caps the I/O route at ~${rounded(ceiling.predicted_rps)} req/s`,
            detail: `Each request there holds a worker for ${ceiling.io_ms}ms, so ${ceiling.workers} workers cannot beat that however fast the machine is. It flattened at ${rounded(ceiling.observed_rps)}, right on the line.`,
            fix: 'Raise the worker count to move the line.',
        });
    }

    // A route that gave out has a better answer than "it was still climbing",
    // and what it answered at that level is what says where to look.
    const broken = brokenRoutes(http);

    if (broken.length > 0) {
        found.push({ key: 'breaking-point', severity: 'medium', ...brokenCaveat(http, env, broken[0]) });
    }

    // Routes that broke are explained above; calling them "still climbing"
    // names the symptom and hides the cause.
    const brokenKeys = broken.map((route) => route.key);
    const unsaturated = unsaturatedRoutes(http).filter((key) => ! brokenKeys.includes(key));

    if (unsaturated.length > 0) {
        found.push({ key: 'not-saturated', severity: 'medium', ...unsaturatedCaveat(http, unsaturated) });
    }

    // Context, not a defect: the self-test is the zero-setup default and the
    // right instrument for comparing configurations on one machine.
    if (Object.keys(http.routes ?? {}).length > 0 && selfTested(http)) {
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
