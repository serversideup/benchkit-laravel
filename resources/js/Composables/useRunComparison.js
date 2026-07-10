// Diffs two run snapshots: which settings/environment changed, and metric
// deltas for the stages both runs completed. Shared by the compare page and
// the comparison share template.

const SETTING_LABELS = {
    hardware: 'Hardware tests',
    disk: 'Disk test',
    geekbench: 'Geekbench',
    geekbench_version: 'Geekbench version',
    iperf: 'iperf',
    network: 'Network test',
    network_test_type: 'Network protocol',
    http: 'Web server load test',
    http_duration: 'Load test duration',
    http_connections: 'Load test connections',
    php_database: 'PHP benchmarks',
    php_mode: 'PHP suite',
};

const ENVIRONMENT_PATHS = [
    { path: 'php.php_variation', label: 'PHP variation' },
    { path: 'php.php_version', label: 'PHP version' },
    { path: 'php.octane', label: 'Octane' },
    { path: 'php.op_cache', label: 'OPcache' },
    { path: 'php.op_cache_jit', label: 'OPcache JIT' },
    { path: 'php.memory_limit', label: 'PHP memory limit' },
    { path: 'laravel.environment.laravel_version', label: 'Laravel version' },
    { path: 'laravel.drivers.database', label: 'Database' },
    { path: 'server.cpu_model', label: 'CPU' },
    { path: 'server.cpu_cores', label: 'CPU cores' },
    { path: 'server.ram', label: 'RAM' },
    { path: 'server.os', label: 'OS' },
    { path: 'build_version', label: 'BenchKit build' },
];

import { headlineMilliseconds } from '@/Composables/useRunSummary';

// The full phpbench suite runs the same subjects every time, so the total
// mean is comparable between runs. Null in quick mode (headline-only) —
// null values are dropped from the delta table automatically.
const suiteTotalMs = (php) => {
    const subjects = php?.subjects ?? [];

    if( subjects.length <= 4 ) {
        return null;
    }

    return Math.round(subjects.reduce((sum, subject) => sum + (subject.mean_us ?? 0), 0) / 1000);
};

// betterWhen tells the UI which direction to paint green. DB read leads —
// same realism-first ordering as the run page and share card.
export const METRICS = {
    http: [
        { path: 'routes.db_read.requests_per_second', label: 'DB read', unit: 'req/s', betterWhen: 'higher' },
        { path: 'routes.json.requests_per_second', label: 'JSON API', unit: 'req/s', betterWhen: 'higher' },
        { path: 'routes.static.requests_per_second', label: 'Static', unit: 'req/s', betterWhen: 'higher' },
        { path: 'routes.db_read.p50_ms', label: 'DB read p50', unit: 'ms', betterWhen: 'lower' },
        { path: 'routes.db_read.p95_ms', label: 'DB read p95', unit: 'ms', betterWhen: 'lower' },
        { path: 'routes.db_read.p99_ms', label: 'DB read p99', unit: 'ms', betterWhen: 'lower' },
        // Success only earns a row when something actually failed — 100%
        // on both sides is the expected state, not information
        { accessor: (http) => http?.routes?.db_read?.success_rate != null ? http.routes.db_read.success_rate * 100 : null, path: 'success_rate', label: 'Success rate', unit: '%', betterWhen: 'higher', hideWhenBothAre: 100 },
    ],
    php: [
        { accessor: (php) => headlineMilliseconds(php, 'create'), path: 'headline.create.milliseconds', label: 'Create · 100 records', unit: 'ms', betterWhen: 'lower' },
        { accessor: (php) => headlineMilliseconds(php, 'read'), path: 'headline.read.milliseconds', label: 'Read · 100 records', unit: 'ms', betterWhen: 'lower' },
        { accessor: (php) => headlineMilliseconds(php, 'update'), path: 'headline.update.milliseconds', label: 'Update · 100 records', unit: 'ms', betterWhen: 'lower' },
        { accessor: (php) => headlineMilliseconds(php, 'delete'), path: 'headline.delete.milliseconds', label: 'Delete · 100 records', unit: 'ms', betterWhen: 'lower' },
        { accessor: suiteTotalMs, path: 'suite_total', label: 'PHP suite total', unit: 'ms', betterWhen: 'lower' },
    ],
    cfspeedtest: [
        { path: 'download_mbps', label: 'Download', unit: 'mbps', betterWhen: 'higher' },
        { path: 'upload_mbps', label: 'Upload', unit: 'mbps', betterWhen: 'higher' },
        { path: 'latency_ms', label: 'Latency', unit: 'ms', betterWhen: 'lower' },
    ],
    yabs: [
        { path: 'geekbench.0.single', label: 'Geekbench single-core', unit: '', betterWhen: 'higher' },
        { path: 'geekbench.0.multi', label: 'Geekbench multi-core', unit: '', betterWhen: 'higher' },
    ],
};

export const STAGE_LABELS = {
    yabs: 'Hardware',
    cfspeedtest: 'Network speed test',
    http: 'Web server load test',
    php: 'Laravel database performance',
};

const get = (object, path) => path.split('.').reduce((value, key) => value?.[key], object);

const formatValue = (value) => {
    if( value === null || value === undefined ) {
        return '—';
    }

    if( typeof value === 'boolean' ) {
        return value ? 'On' : 'Off';
    }

    return String(value);
};

// Raw setting values are machine-speak — spell them the way a person
// would say them
const SETTING_FORMATS = {
    php_mode: (value) => ({ full: 'Full suite', quick: 'Quick' })[value] ?? value,
    network_test_type: (value) => typeof value === 'string' ? value.replace(/^ipv/i, 'IPv') : value,
    geekbench_version: (value) => value != null ? `v${value}` : value,
    http_duration: (value) => value != null ? `${value}s` : value,
};

// The single most meaningful delta to lead with — same realism-first
// priority as the share card and run page
export const headlineDelta = (metricDeltas) => {
    const all = Object.values(metricDeltas).flat();
    const candidates = [
        'routes.db_read.requests_per_second',
        'routes.json.requests_per_second',
        'routes.static.requests_per_second',
        'headline.create.milliseconds',
        'geekbench.0.multi',
    ];

    for (const path of candidates) {
        const delta = all.find((candidate) => candidate.path === path);

        if( delta ) {
            return delta;
        }
    }

    return null;
};

export const compareRuns = (runA, runB) => {
    const commonStages = runA.stages_completed.filter((stage) => runB.stages_completed.includes(stage));
    const exclusiveStages = [
        ...runA.stages_completed.filter((stage) => !commonStages.includes(stage)).map((stage) => ({ stage, run: 'A' })),
        ...runB.stages_completed.filter((stage) => !commonStages.includes(stage)).map((stage) => ({ stage, run: 'B' })),
    ];

    // Boolean settings are tests that either ran or didn't — they carry
    // type 'toggle' so the UI can speak Ran/Skipped in the same status
    // vocabulary as the run history, instead of "on → off"
    const settingsDiff = Object.keys(SETTING_LABELS)
        .filter((key) => JSON.stringify(runA.settings?.[key]) !== JSON.stringify(runB.settings?.[key]))
        .map((key) => {
            const a = runA.settings?.[key];
            const b = runB.settings?.[key];

            if( typeof (a ?? b) === 'boolean' ) {
                return { label: SETTING_LABELS[key], type: 'toggle', a: a === true, b: b === true };
            }

            const format = SETTING_FORMATS[key] ?? ((value) => value);

            return { label: SETTING_LABELS[key], type: 'value', a: formatValue(format(a)), b: formatValue(format(b)) };
        });

    const environmentDiff = ENVIRONMENT_PATHS
        .filter(({ path }) => JSON.stringify(get(runA.environment, path)) !== JSON.stringify(get(runB.environment, path)))
        .map(({ path, label }) => ({
            label,
            type: 'value',
            a: formatValue(get(runA.environment, path)),
            b: formatValue(get(runB.environment, path)),
        }));

    const metricDeltas = {};

    commonStages.forEach((stage) => {
        const deltas = (METRICS[stage] ?? [])
            .map((metric) => {
                const a = metric.accessor ? metric.accessor(runA.benchmarks[stage]) : get(runA.benchmarks[stage], metric.path);
                const b = metric.accessor ? metric.accessor(runB.benchmarks[stage]) : get(runB.benchmarks[stage], metric.path);

                if( typeof a !== 'number' || typeof b !== 'number' ) {
                    return null;
                }

                if( metric.hideWhenBothAre !== undefined && a === metric.hideWhenBothAre && b === metric.hideWhenBothAre ) {
                    return null;
                }

                const delta = b - a;
                const percent = a !== 0 ? (delta / a) * 100 : null;
                const improved = Math.abs(percent ?? 0) < 1
                    ? null
                    : (metric.betterWhen === 'higher' ? delta > 0 : delta < 0);

                return { ...metric, a, b, delta, percent, improved };
            })
            .filter(Boolean);

        if( deltas.length ) {
            metricDeltas[stage] = deltas;
        }
    });

    return { commonStages, exclusiveStages, settingsDiff, environmentDiff, metricDeltas };
};
