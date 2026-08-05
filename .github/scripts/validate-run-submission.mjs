#!/usr/bin/env node
// Validates community result submissions (docs/content/runs/*.json) without any
// npm dependencies, so it runs on a bare Node runner. Mirrors the `runs`
// collection schema in docs/content.config.ts, plus free-text sanitization and
// plausibility bounds a schema alone can't express.
//
// As a module:  import { validateSubmission } from './validate-run-submission.mjs'
//               validateSubmission(doc, 'name.json') -> { errors, warnings }
// As a CLI:     node validate-run-submission.mjs [file ...]
//               No args validates every docs/content/runs/*.json. Exit 1 on any
//               error. Writes a markdown report to $GITHUB_STEP_SUMMARY if set.

import { readFileSync, readdirSync, existsSync, appendFileSync } from 'node:fs';
import { basename } from 'node:path';

const RUNS_DIR = 'docs/content/runs';
const ID_RE = /^[0-9]{8}-[0-9]{6}-[a-z0-9]+$/;
const GITHUB_USER_RE = /^[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}$/i;
const KNOWN_VARIATIONS = ['frankenphp', 'fpm-nginx', 'fpm-apache'];
const MAX_TEXT = 80;
const MAX_RPS = 5_000_000;
const MAX_MS = 600_000;

// Pure validation of one parsed document. filename is used only for the
// id-matches-filename check (pass null to skip it).
export function validateSubmission(doc, filename = null) {
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
        return { errors: ['Top level must be an object { submission, run }'], warnings };
    }
    const run = doc.run;
    if (typeof run !== 'object' || run === null) {
        return { errors: ['Missing `run` object'], warnings };
    }

    // ---- submission (github comes from the authenticated issue author) ----
    if (doc.submission != null) {
        const gh = doc.submission.github;
        if (gh != null && typeof gh !== 'string') err('submission.github must be a string');
        if (typeof gh === 'string' && gh !== '' && !GITHUB_USER_RE.test(gh)) warn(`submission.github "${gh}" doesn't look like a GitHub username`);
        if (doc.submission.verified != null && typeof doc.submission.verified !== 'boolean') err('submission.verified must be a boolean');
        // The Maintainer badge isn't self-serve — flag for the reviewer, don't block.
        if (doc.submission.verified === true) warn('submission.verified is true — the Maintainer badge should only be set on team-added runs');
    }

    // ---- identity ----
    if (typeof run.id !== 'string' || !ID_RE.test(run.id)) {
        err(`run.id "${run.id}" is not a valid run id (expected e.g. 20260805-152622-l9ft)`);
    } else if (filename && basename(filename) !== `${run.id}.json`) {
        err(`filename must match run.id — expected ${run.id}.json, got ${basename(filename)}`);
    }
    if (run.schema_version !== 1) warn(`schema_version is ${run.schema_version} (validator knows version 1)`);
    if (typeof run.created_at !== 'string' || Number.isNaN(Date.parse(run.created_at))) err('run.created_at must be an ISO date string');

    // ---- meta ----
    const meta = run.meta ?? {};
    isText(meta.label, 'meta.label');
    for (const key of ['provider', 'plan', 'datacenter']) {
        if (meta[key] != null) isText(meta[key], `meta.${key}`, { max: 60, required: false });
    }
    if (meta.cost != null && typeof meta.cost !== 'number' && typeof meta.cost !== 'string') err('meta.cost must be a number, string, or null');

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

    return { errors, warnings };
}

// ---- CLI ----
const isMain = import.meta.url === `file://${process.argv[1]}`;
if (isMain) {
    const targets = process.argv.slice(2).filter(Boolean);
    const files = targets.length
        ? targets
        : (existsSync(RUNS_DIR) ? readdirSync(RUNS_DIR).filter(f => f.endsWith('.json')).map(f => `${RUNS_DIR}/${f}`) : []);

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
        report.push(`### ${status} — \`${basename(file)}\``);
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
