// Builds a community-gallery submission from a run and opens a pre-filled
// GitHub *issue*. A bot (.github/workflows/action_process-result-submission.yml)
// reads the JSON, records the issue author as the submitter, validates it, opens
// the PR, and closes the issue. The submitter never forks, edits a file, or
// types their username — GitHub already knows who they are.

import { normalizeCost } from '@/cost';

const REPO = 'serversideup/benchkit-laravel';

// Real bug reports never carry this, so the bot ignores everything else.
const MARKER = '<!-- benchkit-result-submission -->';

// Status codes turn "fast" into "fast and actually served 200s" — a run that
// 500s under load should be visible, not averaged in silently.
const trimStatusCodes = (codes) => {
    if( !codes || typeof codes !== 'object' ) {
        return undefined;
    }

    const kept = Object.entries(codes)
        .filter(([code, count]) => /^\d{3}$/.test(code) && typeof count === 'number')
        .slice(0, 20);

    return kept.length ? Object.fromEntries(kept) : undefined;
};

const trimRoute = (route) => route && {
    path: route.path,
    requests_per_second: route.requests_per_second,
    success_rate: route.success_rate,
    p50_ms: route.p50_ms,
    p95_ms: route.p95_ms,
    p99_ms: route.p99_ms,
    total_requests: route.total_requests,
    status_codes: trimStatusCodes(route.status_codes),
};

// The php.ini knobs worth publishing. Mirrors PhpSpecs::INI_KEYS with one
// deliberate omission: opcache.preload is a filesystem path, which would
// expose the submitter's directory layout (and often a project or company
// name). Whether preloading is on is the part that explains the number, so
// that's what ships — see opcache.preload_enabled below.
const INI_KEYS = [
    'opcache.enable',
    'opcache.enable_cli',
    'opcache.jit',
    'opcache.jit_buffer_size',
    'opcache.memory_consumption',
    'opcache.max_accelerated_files',
    'opcache.validate_timestamps',
    'opcache.revalidate_freq',
    'memory_limit',
    'max_execution_time',
    'realpath_cache_size',
    'realpath_cache_ttl',
    'zend.assertions',
];

const trimIni = (ini) => {
    if( !ini || typeof ini !== 'object' ) {
        return undefined;
    }

    const kept = INI_KEYS
        .filter((key) => ini[key] !== undefined && ini[key] !== null && ini[key] !== false)
        .map((key) => [key, String(ini[key]).slice(0, 40)]);

    return Object.fromEntries([...kept, ['opcache.preload_enabled', Boolean(ini['opcache.preload'])]]);
};

// pm is one of three words and max_children is digits (PhpSpecs extracts them
// with those exact shapes), so anything else means we misread a pool file and
// the safe answer is to publish nothing.
const FPM_MODES = ['static', 'dynamic', 'ondemand'];

const trimServing = (serving) => {
    if( !serving || typeof serving !== 'object' ) {
        return undefined;
    }

    const trimmed = {};

    if( FPM_MODES.includes(serving.fpm_pm) ) {
        trimmed.fpm_pm = serving.fpm_pm;
    }

    const children = Number.parseInt(serving.fpm_max_children, 10);

    if( Number.isFinite(children) && children > 0 ) {
        trimmed.fpm_max_children = children;
    }

    return Object.keys(trimmed).length ? trimmed : undefined;
};

// Which BenchKit built the run, so a change in the app itself is
// distinguishable from a change in the hardware. A self-built image can be
// tagged anything, including "ghcr.io/acme-corp/benchkit", so only a plain
// version-like tag is published — anything else is dropped rather than
// guessed at.
const trimBuildVersion = (value) => (
    typeof value === 'string' && /^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/.test(value) ? value : undefined
);

const trimSapi = (value) => (
    typeof value === 'string' && /^[A-Za-z0-9+-]{1,30}$/.test(value) ? value : undefined
);

// phpbench subject rows: our own class and method names plus a mean. Names are
// matched against an identifier shape so a malformed CSV can't smuggle text
// through this field.
const IDENTIFIER = /^[A-Za-z0-9_]{1,60}$/;

const trimSubjects = (subjects) => {
    if( !Array.isArray(subjects) ) {
        return undefined;
    }

    const kept = subjects
        .filter((row) => IDENTIFIER.test(row?.benchmark ?? '') && IDENTIFIER.test(row?.subject ?? '') && typeof row?.mean_us === 'number')
        .slice(0, 100)
        .map((row) => ({ benchmark: row.benchmark, subject: row.subject, mean_us: row.mean_us }));

    return kept.length ? kept : undefined;
};

// Allow-list, never drop-list: the stored run carries the submitter's public
// IP (in logs) and their APP_URL / internal hostname (http.target,
// laravel.environment.url). None of that belongs in a public file, so we copy
// only fields chosen deliberately.
export const trimRunForSubmission = (run) => {
    const environment = run.environment ?? {};
    const server = environment.server ?? {};
    const php = environment.php ?? {};
    const laravel = environment.laravel ?? {};
    const benchmarks = run.benchmarks ?? {};

    const http = benchmarks.http
        ? {
            mode: benchmarks.http.mode,
            duration_seconds: benchmarks.http.duration_seconds,
            connections: benchmarks.http.connections,
            io_ms: benchmarks.http.io_ms,
            // Pool size is config, not identity, and without it a reader can't
            // tell a framework result from one bounded by pm.max_children.
            fpm_max_children: benchmarks.http.fpm_max_children,
            pool_limited: benchmarks.http.pool_limited,
            routes: Object.fromEntries(
                ['static', 'json', 'db_read', 'io']
                    .filter((key) => benchmarks.http.routes?.[key])
                    .map((key) => [key, trimRoute(benchmarks.http.routes[key])]),
            ),
        }
        : null;

    const phpBench = benchmarks.php?.headline
        ? { headline: benchmarks.php.headline, subjects: trimSubjects(benchmarks.php.subjects) }
        : null;

    // Hardware benchmarks live under yabs in the app doc; flatten to clean
    // gallery shapes (same mapping runDisplay uses for the app's Hardware panel).
    const geek = benchmarks.yabs?.geekbench?.[0];
    const geekbench = geek && geek.single && geek.multi
        ? {
            single: geek.single,
            multi: geek.multi,
            version: geek.version ?? run.settings?.geekbench_version ?? null,
            url: run.extras?.geekbench_url ?? geek.url ?? null,
        }
        : null;

    const disk = Array.isArray(benchmarks.yabs?.fio) && benchmarks.yabs.fio.length
        ? benchmarks.yabs.fio.map((row) => ({
            bs: row.bs,
            speed_r: row.speed_r,
            speed_w: row.speed_w,
            speed_rw: row.speed_rw,
        }))
        : null;

    const cfspeedtest = benchmarks.cfspeedtest
        ? {
            // ASN and colo stay local. On a datacenter run they name the host,
            // but on someone's home hardware they are their residential ISP
            // and their nearest city — and the speeds are the part the
            // community is comparing anyway.
            latency_ms: benchmarks.cfspeedtest.latency_ms ?? null,
            download_mbps: benchmarks.cfspeedtest.download_mbps ?? null,
            upload_mbps: benchmarks.cfspeedtest.upload_mbps ?? null,
        }
        : null;

    return {
        schema_version: run.schema_version ?? 1,
        id: run.id,
        created_at: run.created_at,
        meta: {
            label: run.meta?.label ?? 'BenchKit run',
            provider: run.meta?.provider ?? null,
            plan: run.meta?.plan ?? null,
            datacenter: run.meta?.datacenter ?? null,
            // Structured, always monthly, currency as billed. Runs snapshotted
            // before cost was structured still hold a free-text string, so
            // normalize on the way out rather than shipping two shapes into
            // the public gallery.
            cost: normalizeCost(run.meta?.cost),
        },
        // Which benchmarks ran and how they were configured — without these a
        // number can't be compared to another number.
        settings_preset: run.settings_preset ?? null,
        stages_completed: Array.isArray(run.stages_completed) ? run.stages_completed : [],
        environment: {
            server: {
                cpu_model: server.cpu_model,
                cpu_cores: server.cpu_cores,
                cpu_frequency: server.cpu_frequency,
                os: server.os,
                ram: server.ram,
            },
            php: {
                php_version: php.php_version,
                php_variation: php.php_variation,
                php_server_api: trimSapi(php.php_server_api),
                octane: php.octane,
                op_cache: php.op_cache,
                memory_limit: php.memory_limit,
                // The "which knob moved the number" data: JIT, opcache sizing,
                // and worker counts explain more of the spread between two
                // runs than the hardware often does.
                ini: trimIni(php.ini),
                serving: trimServing(php.serving),
            },
            laravel: {
                environment: {
                    laravel_version: laravel.environment?.laravel_version,
                },
                drivers: laravel.drivers ?? undefined,
            },
            build_version: trimBuildVersion(environment.build_version),
        },
        benchmarks: { http, php: phpBench, cfspeedtest, geekbench, disk },
    };
};

export const buildSubmissionIssueBody = (run) => [
    MARKER,
    'Submitting my BenchKit results to the community gallery. A bot files this automatically — just click **Submit new issue** below.',
    '',
    '### Results data',
    '```json',
    JSON.stringify(trimRunForSubmission(run), null, 2),
    '```',
    '',
    '_Generated by the BenchKit app. Your GitHub username is recorded automatically as the submitter._',
].join('\n');

export const buildSubmissionIssueUrl = (run) => {
    const label = run.meta?.label ?? 'BenchKit run';

    const params = new URLSearchParams({
        title: `Result: ${label}`,
        labels: 'result-submission',
        body: buildSubmissionIssueBody(run),
    });

    return `https://github.com/${REPO}/issues/new?${params.toString()}`;
};

export const openSubmissionIssue = (run) => {
    window.open(buildSubmissionIssueUrl(run), '_blank', 'noopener');
};
