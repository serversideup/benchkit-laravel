import { serverLabelFor } from '@/Composables/useRunSummary';

export const REPO_URL = 'https://github.com/serversideup/benchkit-laravel';

// Same route priority as the share card's hero: DB read is the closest
// thing to a real Laravel page
const heroRoute = (run) => {
    const routes = run.benchmarks?.http?.routes ?? {};

    for (const key of ['db_read', 'json', 'static']) {
        if( routes[key]?.requests_per_second != null ) {
            return routes[key];
        }
    }

    return null;
};

const heroRps = (run) => heroRoute(run)?.requests_per_second ?? run.summary?.http_rps ?? null;

// One idea per line so nothing wraps into a wall of text:
// performance, then stack, then what it costs
const performanceLineFor = (run) => {
    const route = heroRoute(run);

    if( route ) {
        return [
            `${Math.round(route.requests_per_second).toLocaleString('en-US')} req/s`,
            route.p95_ms != null ? `p95 ${Math.round(route.p95_ms).toLocaleString('en-US')}ms` : null,
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

    return [meta.provider, meta.plan ?? meta.plan_notes, meta.cost].filter(Boolean).join(' · ');
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
