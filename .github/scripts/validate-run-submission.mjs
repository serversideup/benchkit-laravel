#!/usr/bin/env node
// Filesystem and CLI half of the run-submission validator.
//
// The rules themselves live in docs/shared/submission/validate.mjs, with no
// Node-only imports, so the site's /results/submit page can run the exact same
// checks in the browser and tell a submitter what's wrong before they open an
// issue. This file is what the GitHub Action invokes.
//
// Usage: node validate-run-submission.mjs [file ...]
//        No args validates every docs/data/runs/**/*.json. Exit 1 on any error.
//        Writes a markdown report to $GITHUB_STEP_SUMMARY if set.

import { readFileSync, readdirSync, existsSync, appendFileSync } from 'node:fs';
import { join } from 'node:path';
import { validateSubmission } from '../../docs/shared/submission/validate.mjs';

export { validateSubmission };

const RUNS_DIR = 'docs/data/runs';

// Runs are sharded into month directories, so walk rather than list.
const findRunFiles = (dir) => readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) return findRunFiles(path);
    return entry.name.endsWith('.json') ? [path] : [];
});

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
            result = await validateSubmission(JSON.parse(readFileSync(file, 'utf8')), file);
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
