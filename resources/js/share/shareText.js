import { hostDetailsLine, serverLabelFor } from '@/Composables/useRunSummary';

const REPO_URL = 'https://github.com/serversideup/benchkit-laravel';

// Same route priority as the share card's hero and the gallery's ranking, so
// the number in the post is the number in the image is the number the result
// is ranked by.
//
// This used to lead with DB read while the card led with JSON, which meant a
// post and its own image could quote different routes. JSON is the right one
// of the two: the database is a confound the hardware has nothing to do with,
// and SQLite on tmpfs against Postgres over a socket differ by more than two
// machines do.
const heroRoute = (run) => {
    const routes = run.benchmarks?.http?.routes ?? {};

    for (const key of ['json', 'static', 'db_read']) {
        if( routes[key]?.throughput?.requests_per_second != null ) {
            return routes[key];
        }
    }

    return null;
};

const heroRps = (run) => heroRoute(run)?.throughput?.requests_per_second ?? run.summary?.http_rps ?? null;

// One idea per line so nothing wraps into a wall of text:
// performance, then stack, then what it costs
const performanceLineFor = (run) => {
    const route = heroRoute(run);

    if( route ) {
        // The response time comes from a separate pass below the peak, so
        // these two are independent facts rather than one measurement said
        // twice. A floor is marked as one.
        const throughput = route.throughput ?? {};
        const prefix = throughput.saturated === false ? '≥' : '';

        return [
            `${prefix}${Math.round(throughput.requests_per_second).toLocaleString('en-US')} req/s`,
            route.latency?.p50_ms != null ? `${Math.round(route.latency.p50_ms).toLocaleString('en-US')}ms response` : null,
        ].filter(Boolean).join(' · ');
    }

    const createMs = run.benchmarks?.php?.headline?.create?.milliseconds;

    if( createMs != null ) {
        return `${createMs}ms to create ${run.benchmarks.php.headline.create.records ?? 100} records`;
    }

    return null;
};

const stackLineFor = (run) => [
    (serverLabelFor(run) || '').slice(0, 32) || null,
    run.environment?.php?.php_version ? `PHP ${run.environment.php.php_version}` : null,
    run.environment?.laravel?.environment?.laravel_version ? `Laravel ${run.environment.laravel.environment.laravel_version}` : null,
].filter(Boolean).join(' · ') || null;

// The hosting line is the conversation starter — real numbers on real money
const hostLineFor = (run) => {
    const meta = run.meta ?? {};

    if( !meta.provider ) {
        return null;
    }

    return hostDetailsLine(meta, ['provider', 'plan', 'cost']);
};

export const buildRunShareText = (run) => {
    return [
        'Just benchmarked my Laravel stack with #BenchKit 🔥',
        '',
        ...[performanceLineFor(run), stackLineFor(run), hostLineFor(run)].filter(Boolean),
        '',
        'How fast is your host? via @serversideup',
        REPO_URL,
    ].join('\n');
};

export const buildComparisonShareText = (runA, runB) => {
    const labelA = (serverLabelFor(runA) || runA.meta.label || 'A').slice(0, 32);
    const labelB = (serverLabelFor(runB) || runB.meta.label || 'B').slice(0, 32);
    const rpsA = heroRps(runA);
    const rpsB = heroRps(runB);

    const lead = rpsA && rpsB
        ? `🔥 ${labelA} vs ${labelB}: ${Math.round(rpsA).toLocaleString('en-US')} → ${Math.round(rpsB).toLocaleString('en-US')} req/s`
        : `🔥 ${labelA} vs ${labelB} — benchmarked head to head`;

    return [
        lead,
        '',
        'My #BenchKit comparison by @serversideup — how fast is your host?',
        '',
        REPO_URL,
    ].join('\n');
};
