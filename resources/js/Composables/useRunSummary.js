// Pure mapping from a run snapshot document (the JSON stored on the runs
// disk) to display-ready values. No fetches — the snapshot is self-contained.

import { formatCost } from '@/cost';

export const formatUTCTimestamp = (isoString) => {
    const date = new Date(isoString);
    const pad = n => n < 10 ? '0' + n : n;

    return `${pad(date.getUTCDate())}/${pad(date.getUTCMonth() + 1)}/${date.getUTCFullYear()} ${date.getUTCHours()}:${pad(date.getUTCMinutes())} UTC`;
};

// Sub-millisecond times auto-scale to µs (matching the phpbench subjects
// table) — "0.1ms" hides the difference between 89µs and 136µs
export const formatMsParts = (ms) => {
    if( ms === null || ms === undefined ) {
        return null;
    }

    if( ms < 1 ) {
        return { value: String(Math.round(ms * 1000)), unit: 'µs' };
    }

    return { value: String(parseFloat(Number(ms).toFixed(1))), unit: 'ms' };
};

export const formatMs = (ms) => {
    const parts = formatMsParts(ms);

    return parts ? `${parts.value}${parts.unit}` : 'N/A';
};

// Elapsed milliseconds as a mm:ss clock
export const formatClock = (milliseconds) => {
    const seconds = Math.max(0, Math.floor(milliseconds / 1000));
    const pad = (n) => String(n).padStart(2, '0');

    return `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`;
};

// yabs reports capacities as raw values with units like "KB"/"KiB"/"MB"
export const formatCapacity = (value, units = 'KB') => {
    const number = parseFloat(value);

    if( isNaN(number) ) {
        return null;
    }

    const multipliers = { B: 1, KB: 1024, KiB: 1024, MB: 1024 ** 2, MiB: 1024 ** 2, GB: 1024 ** 3, GiB: 1024 ** 3 };
    const bytes = number * (multipliers[units] ?? 1);
    const gigabytes = bytes / 1024 ** 3;

    if( gigabytes >= 1024 ) {
        return `${(gigabytes / 1024).toFixed(1)} TB`;
    }

    return gigabytes >= 10 ? `${Math.round(gigabytes)} GB` : `${gigabytes.toFixed(1)} GB`;
};

// fio speeds arrive in MB/s and can reach absurd cached-I/O magnitudes
export const formatThroughput = (megabytesPerSecond) => {
    if( megabytesPerSecond == null ) {
        return '—';
    }

    if( megabytesPerSecond >= 1024 ** 2 ) {
        return `${(megabytesPerSecond / 1024 ** 2).toFixed(1)} TB/s`;
    }

    if( megabytesPerSecond >= 1024 ) {
        return `${(megabytesPerSecond / 1024).toFixed(1)} GB/s`;
    }

    return `${Math.round(megabytesPerSecond).toLocaleString()} MB/s`;
};

// Hosting meta as a single display line, e.g. "Premium AMD 2GB · NYC3 ·
// $24/mo". `plan_notes` is the pre-split legacy field on older snapshots,
// so it stands in when `plan` is absent.
export const hostDetailsLine = (meta = {}, fields = ['provider', 'plan', 'datacenter', 'cost']) => {
    // cost is stored structured; every caller here wants it as "€20/mo"
    const values = { ...meta, plan: meta.plan ?? meta.plan_notes, cost: formatCost(meta.cost) };

    return fields.map((field) => values[field]).filter(Boolean).join(' · ');
};

export const serverLabelFor = (run) => {
    const php = run.environment?.php ?? {};
    const base = php.php_variation || php.php_server_api;

    if( !base ) {
        return null;
    }

    return php.octane ? `${base} + octane` : base;
};

// Mirrors PhpBenchmarkResults::HEADLINE_SUBJECTS — the raw subject means
// carry full µs precision, while older snapshots stored the headline
// pre-rounded to one decimal (136µs flattened to "0.1"), so the subject
// value wins whenever it exists
const HEADLINE_SUBJECTS = {
    create: ['InsertBenchmark', 'benchDbFacadeInsertIndividual'],
    read: ['QueryBenchmark', 'benchSelectIndividualById'],
    update: ['UpdateBenchmark', 'benchQueryBuilderIndividual'],
    delete: ['DeleteBenchmark', 'benchQueryBuilderIndividual'],
};

export const headlineMilliseconds = (php, key) => headlineOperation(php ?? {}, key).milliseconds ?? null;

// Above this relative standard deviation, in percent, the iterations behind a
// mean disagreed enough that the mean stops describing them, and the number
// gets shown as the estimate it is. Mirrors
// PhpBenchmarkResults::HIGH_VARIANCE_RSTDEV.
export const HIGH_VARIANCE_RSTDEV = 10;

export const isHighVariance = (rstdev) => typeof rstdev === 'number' && rstdev > HIGH_VARIANCE_RSTDEV;

const CRUD_OPERATIONS = [
    { key: 'create', label: 'Create', detail: 'INSERT per record' },
    { key: 'read', label: 'Read', detail: 'SELECT per record, by id' },
    { key: 'update', label: 'Update', detail: 'UPDATE per record, by id' },
    { key: 'delete', label: 'Delete', detail: 'DELETE per record, by id' },
];

/**
 * The four CRUD tiles, and whether they may share a bar scale.
 *
 * They may only when they measured the same unit of work, which is what
 * `statements` records. Runs from before schema 3 measured read as one SELECT
 * returning 100 rows while the other three ran 100 statements — roughly a
 * hundredth of the work, and it looked proportionally faster. Those runs carry
 * no statement count, so they get no shared scale and say why.
 */
export const crudHeadlines = (php) => {
    const entries = CRUD_OPERATIONS
        .map((operation) => ({ ...operation, data: headlineOperation(php ?? {}, operation.key) }))
        .filter((operation) => operation.data.milliseconds != null);

    const statements = entries.map((operation) => operation.data.statements);
    const comparable = entries.length > 0 && statements.every((count) => count != null && count === statements[0]);
    const maxMs = Math.max(1, ...entries.map((operation) => operation.data.milliseconds));

    return {
        comparable,
        records: entries[0]?.data.records ?? 100,
        operations: entries.map((operation) => ({
            key: operation.key,
            label: operation.label,
            detail: operation.detail,
            milliseconds: operation.data.milliseconds,
            rstdev: operation.data.rstdev ?? null,
            iterations: operation.data.iterations ?? null,
            percent: comparable ? Math.max(2, (operation.data.milliseconds / maxMs) * 100) : null,
        })),
    };
};

// How the load test reached the app (recorded in http-meta.json). Loopback
// is the standard, comparable path; the other modes are disclosed so a
// shared result always states what was actually measured.
export const httpTargetLabel = (mode) => ({
    'loopback': 'loopback',
    'app-url': 'via APP_URL',
    'custom': 'custom URL',
}[mode] ?? null);

const headlineOperation = (php, key) => {
    const headline = php.headline?.[key] ?? {};
    const [benchmark, subject] = HEADLINE_SUBJECTS[key];
    const measured = (php.subjects ?? []).find((entry) => entry.benchmark === benchmark && entry.subject === subject);

    return measured?.mean_us != null
        ? { ...headline, milliseconds: measured.mean_us / 1000 }
        : headline;
};

export const runDisplay = (run) => {
    const stages = Object.fromEntries(
        ['yabs', 'cfspeedtest', 'http', 'php'].map((stage) => [stage, run.stages_completed.includes(stage)]),
    );

    const http = run.benchmarks.http;
    const php = run.benchmarks.php;
    const network = run.benchmarks.cfspeedtest;
    const yabs = run.benchmarks.yabs;
    const geekbench = yabs?.geekbench?.[0] ?? null;

    return {
        stages,
        http: http ? {
            rps: http.routes?.static?.requests_per_second ?? null,
            p95: http.routes?.static?.p95_ms ?? null,
            json_rps: http.routes?.json?.requests_per_second ?? null,
            db_rps: http.routes?.db_read?.requests_per_second ?? null,
            mode: http.mode ?? null,
            octane: run.environment?.php?.octane ?? false,
            duration_seconds: http.duration_seconds ?? null,
            connections: http.connections ?? null,
            io_ms: http.io_ms ?? null,
            routes: http.routes ?? {},
        } : null,
        php: php ? {
            create: headlineOperation(php, 'create'),
            read: headlineOperation(php, 'read'),
            update: headlineOperation(php, 'update'),
            delete: headlineOperation(php, 'delete'),
            subjects: php.subjects ?? [],
        } : null,
        network: network ? {
            download: network.download_mbps,
            upload: network.upload_mbps,
            latency: network.latency_ms,
            colo: network.colo,
            asn: network.asn,
        } : null,
        geekbench: geekbench && geekbench.single && geekbench.multi ? {
            single: geekbench.single,
            multi: geekbench.multi,
            version: geekbench.version ?? run.settings?.geekbench_version ?? null,
            url: run.extras?.geekbench_url ?? geekbench.url ?? null,
        } : null,
        hardware: yabs ? {
            cpu: yabs.cpu ?? null,
            mem: yabs.mem ?? null,
            os: yabs.os ?? null,
            fio: yabs.fio ?? [],
        } : null,
        environment: {
            serverLabel: serverLabelFor(run),
            octane: run.environment?.php?.octane ?? false,
            phpVersion: run.environment?.laravel?.environment?.php_version ?? run.environment?.php?.php_version ?? null,
            laravelVersion: run.environment?.laravel?.environment?.laravel_version ?? null,
            database: run.environment?.laravel?.drivers?.database ?? null,
        },
        timestamp: formatUTCTimestamp(run.created_at),
    };
};
