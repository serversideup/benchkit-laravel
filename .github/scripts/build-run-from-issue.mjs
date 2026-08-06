#!/usr/bin/env node
// Turns a result-submission issue into a validated docs/data/runs file.
// Identity comes from the authenticated issue author (ISSUE_AUTHOR), never from
// the body — so it can't be spoofed. Nothing here executes issue content; it
// only JSON.parses it, so running on arbitrary issues is safe.
//
// Env: ISSUE_BODY, ISSUE_AUTHOR
// Outputs (to $GITHUB_OUTPUT): valid=true|false, id, path, errors

import { writeFileSync, mkdirSync, appendFileSync, existsSync } from 'node:fs';
import { dirname } from 'node:path';
import { validateSubmission } from './validate-run-submission.mjs';
import { buildDocument, runsPathFor } from './run-document.mjs';

const ID_RE = /^[0-9]{8}-[0-9]{6}-[a-z0-9]+$/;

const body = process.env.ISSUE_BODY ?? '';
const author = (process.env.ISSUE_AUTHOR ?? '').trim();
const out = process.env.GITHUB_OUTPUT;

const setOutput = (key, value) => {
    if (!out) return;
    if (String(value).includes('\n')) {
        appendFileSync(out, `${key}<<__EOF__\n${value}\n__EOF__\n`);
    } else {
        appendFileSync(out, `${key}=${value}\n`);
    }
};

// bail: report a human-readable failure but exit 0 — the workflow reads `valid`
// and comments on the issue; a non-zero exit would just look like a crash.
const bail = (...messages) => {
    console.error(messages.join('\n'));
    setOutput('valid', 'false');
    setOutput('errors', messages.join('\n'));
    process.exit(0);
};

// The app wraps the results in a ```json fenced block.
const fence = body.match(/```json\s*([\s\S]*?)```/i);
if (!fence) bail('Could not find a ```json code block with your results in the issue.');

let parsed;
try {
    parsed = JSON.parse(fence[1].trim());
} catch (e) {
    bail(`The results JSON could not be parsed: ${e.message}`);
}

// Accept either the bare run document or a { run } wrapper.
let run = parsed;
if (parsed && typeof parsed === 'object' && parsed.run && !parsed.id) run = parsed.run;

if (!run || typeof run !== 'object') bail('The results JSON is not an object.');

// Validate the id BEFORE using it as a filename — this is the path-traversal guard.
if (typeof run.id !== 'string' || !ID_RE.test(run.id)) {
    bail(`Invalid run id "${run?.id}" — expected something like 20260805-152622-l9ft.`);
}

const path = runsPathFor(run.id);

// A run id is minted once per benchmark, so the same id arriving twice is a
// resubmission, not a new data point. Without this the branch force-pushes over
// itself and `gh pr create` fails on an existing head, which reads as a crash.
if (existsSync(path)) {
    bail(`Run ${run.id} is already in the gallery — re-run the benchmark to submit a fresh result.`);
}

const doc = buildDocument(run, {
    github: author,
    submittedAt: (run.created_at ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
    verified: false,
});

const { errors } = validateSubmission(doc, path);
if (errors.length) {
    bail('The submission didn\'t pass validation:', ...errors.map(e => `- ${e}`));
}

mkdirSync(dirname(path), { recursive: true });
writeFileSync(path, `${JSON.stringify(doc, null, 2)}\n`);

console.log(`Wrote ${path} for @${author}`);
setOutput('valid', 'true');
setOutput('id', run.id);
setOutput('path', path);
