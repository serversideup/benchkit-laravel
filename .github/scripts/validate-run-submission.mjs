#!/usr/bin/env node
// Validates community result submissions (docs/data/runs/**/*.json) without
// any npm dependencies, so it runs on a bare Node runner. This is the gate: a
// run that fails here never merges, so it's the strictest check in the chain —
// free-text sanitization, plausibility bounds, and index/run consistency, none
// of which a type schema alone can express.
//
// As a module:  import { validateSubmission } from './validate-run-submission.mjs'
//               validateSubmission(doc, 'path.json') -> { errors, warnings }
// As a CLI:     node validate-run-submission.mjs [file ...]
//               No args validates every docs/data/runs/**/*.json. Exit 1 on
//               any error. Writes a markdown report to $GITHUB_STEP_SUMMARY if
//               set.

import { readFileSync, readdirSync, existsSync, appendFileSync } from 'node:fs';
import { join } from 'node:path';
import { CURRENCIES, findPrivacyLeaks, indexFields, runsPathFor } from './run-document.mjs';

const RUNS_DIR = 'docs/data/runs';
const ID_RE = /^[0-9]{8}-[0-9]{6}-[a-z0-9]+$/;
const GITHUB_USER_RE = /^[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}$/i;
const KNOWN_VARIATIONS = ['frankenphp', 'fpm-nginx', 'fpm-apache'];
const MAX_TEXT = 80;
const MAX_RPS = 5_000_000;
const MAX_MS = 600_000;

// Pure validation of one parsed document. filepath is used only for the
// id-matches-path check (pass null to skip it).
export function validateSubmission(doc, filepath = null) {
    const errors = [];
    const warnings = [];
    const err = m => errors.push(m);
    const warn = m => warnings.push(m);

    const isText = (v, label, { max = MAX_TEXT, required = true } = {}) => {
        if (v == null || v === '') {
            if (required) err(`${label} is required`);
            return;
        }
        if (typeof v !== 'string') return err(`${label} must be a string`);
        if (v.length > max) err(`${label} is too long (${v.length} > ${max})`);
        if (/[\x00-\x1F\x7F]/.test(v)) err(`${label} contains control characters`);
        if (/<\s*script|<\/?[a-z][\s\S]*>/i.test(v)) err(`${label} contains HTML/script markup`);
    };

    const isNum = (v, label, { min = -Infinity, max = Infinity, required = true } = {}) => {
        if (v == null) {
            if (required) err(`${label} is required`);
            return;
        }
        if (typeof v !== 'number' || Number.isNaN(v)) return err(`${label} must be a number`);
        if (v < min || v > max) err(`${label} out of range (${v}, expected ${min}..${max})`);
    };

    if (typeof doc !== 'object' || doc === null || Array.isArray(doc)) {
        return { errors: ['Top level must be an object of index fields plus a `run` object'], warnings };
    }
    const run = doc.run;
    if (typeof run !== 'object' || run === null) {
        return { errors: ['Missing `run` object'], warnings };
    }

    // ---- submitter (github comes from the authenticated issue author) ----
    const gh = doc.github;
    if (gh != null && typeof gh !== 'string') err('github must be a string');
    if (typeof gh === 'string' && gh !== '' && !GITHUB_USER_RE.test(gh)) warn(`github "${gh}" doesn't look like a GitHub username`);
    if (doc.verified != null && typeof doc.verified !== 'boolean') err('verified must be a boolean');
    // The Maintainer badge isn't self-serve — flag for the reviewer, don't block.
    if (doc.verified === true) warn('verified is true — the Maintainer badge should only be set on team-added runs');
    if (typeof doc.submitted_at !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(doc.submitted_at)) err('submitted_at must be a YYYY-MM-DD date');

    // ---- identity ----
    if (typeof run.id !== 'string' || !ID_RE.test(run.id)) {
        err(`run.id "${run.id}" is not a valid run id (expected e.g. 20260805-152622-l9ft)`);
    } else if (filepath && filepath.replaceAll('\\', '/') !== runsPathFor(run.id)) {
        err(`file must live at ${runsPathFor(run.id)}, got ${filepath}`);
    }
    if (run.schema_version !== 1) warn(`schema_version is ${run.schema_version} (validator knows version 1)`);
    if (typeof run.created_at !== 'string' || Number.isNaN(Date.parse(run.created_at))) err('run.created_at must be an ISO date string');

    // ---- meta ----
    const meta = run.meta ?? {};
    isText(meta.label, 'meta.label');
    for (const key of ['provider', 'plan', 'datacenter']) {
        if (meta[key] != null) isText(meta[key], `meta.${key}`, { max: 60, required: false });
    }
    // Cost is the one field the gallery does arithmetic on, so it's structured
    // or absent — never free text. Currency is stored as billed and never
    // converted here; the site converts at render with a dated rate table.
    if (meta.cost != null) {
        if (typeof meta.cost !== 'object' || Array.isArray(meta.cost)) {
            err('meta.cost must be an object { amount, currency, period } or null');
        } else {
            isNum(meta.cost.amount, 'meta.cost.amount', { min: 0, max: 1_000_000 });
            if (!CURRENCIES.includes(meta.cost.currency)) err(`meta.cost.currency "${meta.cost.currency}" is not a currency BenchKit records`);
            if (meta.cost.period !== 'monthly') err('meta.cost.period must be "monthly" — every cost is normalized to a month');
        }
    }

    // ---- environment ----
    const env = run.environment ?? {};
    const server = env.server ?? {};
    isText(server.cpu_model, 'environment.server.cpu_model', { max: 120 });
    if (server.cpu_cores == null || !['string', 'number'].includes(typeof server.cpu_cores)) err('environment.server.cpu_cores is required');
    isText(server.os, 'environment.server.os', { max: 120 });
    isText(server.ram, 'environment.server.ram', { max: 40 });

    const php = env.php ?? {};
    isText(php.php_version, 'environment.php.php_version', { max: 20 });
    isText(php.php_variation, 'environment.php.php_variation', { max: 40 });
    if (php.php_variation && !KNOWN_VARIATIONS.includes(php.php_variation)) warn(`unknown php_variation "${php.php_variation}"`);

    if (env.laravel?.environment?.laravel_version == null) err('environment.laravel.environment.laravel_version is required');

    // ---- benchmarks ----
    const benchmarks = run.benchmarks ?? {};
    if (typeof benchmarks !== 'object') err('run.benchmarks must be an object');

    const http = benchmarks.http;
    if (http != null) {
        const routes = http.routes ?? {};
        const routeKeys = Object.keys(routes);
        if (routeKeys.length === 0) err('benchmarks.http.routes has no routes');
        for (const key of routeKeys) {
            const r = routes[key];
            if (r == null) continue;
            isNum(r.requests_per_second, `http.routes.${key}.requests_per_second`, { min: 0, max: MAX_RPS });
            for (const p of ['p50_ms', 'p95_ms', 'p99_ms']) isNum(r[p], `http.routes.${key}.${p}`, { min: 0, max: MAX_MS });
            if (r.success_rate != null) isNum(r.success_rate, `http.routes.${key}.success_rate`, { min: 0, max: 1 });
        }
    }

    const phpBench = benchmarks.php;
    if (phpBench?.headline != null) {
        for (const op of ['create', 'read', 'update', 'delete']) {
            const cell = phpBench.headline[op];
            if (cell != null) isNum(cell.milliseconds, `php.headline.${op}.milliseconds`, { min: 0, max: MAX_MS });
        }
    }

    const cf = benchmarks.cfspeedtest;
    if (cf != null) {
        // On home hardware these are the submitter's ISP and nearest city, and
        // the speeds are what the community is actually comparing.
        for (const key of ['asn', 'colo']) {
            if (cf[key] != null) err(`cfspeedtest.${key} is not published — remove it`);
        }
        for (const [k, bounds] of [['latency_ms', { min: 0, max: MAX_MS }], ['download_mbps', { min: 0, max: 1_000_000 }], ['upload_mbps', { min: 0, max: 1_000_000 }]]) {
            if (cf[k] != null) isNum(cf[k], `cfspeedtest.${k}`, { ...bounds, required: false });
        }
    }

    const gb = benchmarks.geekbench;
    if (gb != null) {
        isNum(gb.single, 'geekbench.single', { min: 0, max: 1_000_000 });
        isNum(gb.multi, 'geekbench.multi', { min: 0, max: 1_000_000 });
        if (gb.url != null && !/^https:\/\/[^\s]+$/.test(String(gb.url))) err('geekbench.url must be an https URL');
    }

    if (benchmarks.disk != null) {
        if (!Array.isArray(benchmarks.disk)) err('benchmarks.disk must be an array');
        else for (const row of benchmarks.disk) {
            isText(row?.bs, 'disk[].bs', { max: 12 });
            for (const k of ['speed_r', 'speed_w', 'speed_rw']) {
                if (row?.[k] != null) isNum(row[k], `disk[].${k}`, { min: 0, max: 10_000_000, required: false });
            }
        }
    }

    if (http == null && phpBench == null) warn('submission has neither HTTP nor PHP benchmarks — it will render nearly empty');

    // ---- environment extras ----
    // Configuration that explains the numbers. Each is bounded here as well as
    // at submission time, because a PR can be hand-edited and this is the only
    // check that runs on it.
    if (php.php_server_api != null && !/^[A-Za-z0-9+-]{1,30}$/.test(String(php.php_server_api))) {
        err('environment.php.php_server_api has an unexpected shape');
    }

    if (php.ini != null) {
        if (typeof php.ini !== 'object' || Array.isArray(php.ini)) {
            err('environment.php.ini must be an object of setting => value');
        } else {
            // opcache.preload is a filesystem path and must never appear; the
            // app publishes opcache.preload_enabled instead.
            if ('opcache.preload' in php.ini) err('environment.php.ini must not include opcache.preload — publish opcache.preload_enabled instead');
            for (const [key, value] of Object.entries(php.ini)) {
                if (!/^[a-z_]+(?:\.[a-z_]+)*$/.test(key)) err(`environment.php.ini key "${key}" has an unexpected shape`);
                if (typeof value === 'string') isText(value, `environment.php.ini["${key}"]`, { max: 40, required: false });
                else if (typeof value !== 'number' && typeof value !== 'boolean') err(`environment.php.ini["${key}"] must be a string, number, or boolean`);
            }
        }
    }

    if (php.serving != null) {
        if (php.serving.fpm_pm != null && !['static', 'dynamic', 'ondemand'].includes(php.serving.fpm_pm)) {
            err(`environment.php.serving.fpm_pm "${php.serving.fpm_pm}" is not a known FPM process manager`);
        }
        if (php.serving.fpm_max_children != null) isNum(php.serving.fpm_max_children, 'environment.php.serving.fpm_max_children', { min: 1, max: 100_000 });
    }

    // A registry-style tag would carry someone's org name into a public file.
    if (env.build_version != null && !/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/.test(String(env.build_version))) {
        err('environment.build_version must be a plain version tag (no registry path)');
    }

    if (run.settings_preset != null && !['quick', 'full', 'custom'].includes(run.settings_preset)) {
        warn(`unknown settings_preset "${run.settings_preset}"`);
    }

    if (run.stages_completed != null && !Array.isArray(run.stages_completed)) {
        err('stages_completed must be an array');
    }

    if (phpBench?.subjects != null) {
        if (!Array.isArray(phpBench.subjects)) err('php.subjects must be an array');
        else for (const row of phpBench.subjects.slice(0, 100)) {
            if (!/^[A-Za-z0-9_]{1,60}$/.test(row?.benchmark ?? '')) err('php.subjects[].benchmark has an unexpected shape');
            if (!/^[A-Za-z0-9_]{1,60}$/.test(row?.subject ?? '')) err('php.subjects[].subject has an unexpected shape');
            isNum(row?.mean_us, 'php.subjects[].mean_us', { min: 0, max: 1e12 });
        }
    }

    // ---- privacy ----
    // Last line of defence, and the only one that covers fields nobody has
    // thought about yet.
    for (const leak of findPrivacyLeaks(doc)) err(leak);

    // ---- index fields ----
    // The flat top-level fields are what the gallery filters and sorts on, and
    // they're derived from `run`. Recompute them: a hand-edited PR that tweaks
    // a number up here would show one figure in the list and another on the
    // detail page, and nobody would notice which one was real.
    for (const [key, expected] of Object.entries(indexFields(run))) {
        const actual = doc[key] ?? null;
        if (actual !== expected) err(`${key} must be ${JSON.stringify(expected)} to match the run, got ${JSON.stringify(actual)}`);
    }

    return { errors, warnings };
}

// Runs are sharded into month directories, so walk rather than list.
const findRunFiles = (dir) => readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) return findRunFiles(path);
    return entry.name.endsWith('.json') ? [path] : [];
});

// ---- CLI ----
const isMain = import.meta.url === `file://${process.argv[1]}`;
if (isMain) {
    const targets = process.argv.slice(2).filter(Boolean);
    const files = targets.length
        ? targets
        : (existsSync(RUNS_DIR) ? findRunFiles(RUNS_DIR) : []);

    const report = [];
    let hadError = false;

    for (const file of files) {
        let result;
        try {
            result = validateSubmission(JSON.parse(readFileSync(file, 'utf8')), file);
        } catch (e) {
            result = { errors: [`Not valid JSON: ${e.message}`], warnings: [] };
        }
        const { errors, warnings } = result;
        if (errors.length) hadError = true;
        const status = errors.length ? '❌ FAIL' : (warnings.length ? '⚠️ PASS (warnings)' : '✅ PASS');
        report.push(`### ${status} — \`${file}\``);
        for (const e of errors) report.push(`- ❌ ${e}`);
        for (const w of warnings) report.push(`- ⚠️ ${w}`);
        console.log(`${status}  ${file}`);
        for (const e of errors) console.log(`   error:   ${e}`);
        for (const w of warnings) console.log(`   warning: ${w}`);
    }

    if (files.length === 0) console.log('No run submission files to validate.');

    const summary = files.length
        ? `## BenchKit result submission check\n\n${report.join('\n')}\n`
        : '## BenchKit result submission check\n\nNo run files changed.\n';
    if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, summary);

    process.exit(hadError ? 1 : 0);
}
