#!/usr/bin/env node
// Turns a result-submission issue into a validated docs/data/runs file.
// Identity comes from the authenticated issue author (ISSUE_AUTHOR), never from
// the body — so it can't be spoofed. Nothing here executes issue content; it
// only decodes and JSON-parses it, so running on arbitrary issues is safe.
//
// Env: ISSUE_BODY, ISSUE_AUTHOR
// Outputs (to $GITHUB_OUTPUT): valid=true|false, id, path, errors

import { writeFileSync, mkdirSync, appendFileSync, existsSync } from 'node:fs';
import { dirname } from 'node:path';
import { validateSubmission } from '../../docs/shared/submission/validate.mjs';
import { buildDocument, runsPathFor } from '../../docs/shared/submission/run-document.mjs';
import { findToken, findFencedPayload, decodeToken, decodePayload, looksLikeToken } from '../../docs/shared/submission/token.mjs';

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

/**
 * Read whichever shape this app build produced. Three are accepted, newest
 * first:
 *
 *   1. a `bk1.<payload>.<checksum>` token anywhere in the body
 *   2. a bare compressed payload inside a ```benchkit fence
 *   3. the original raw ```json fence
 *
 * All three are real submissions from real runs. BenchKit instances are
 * disposable and people run whichever image they happened to pull, so the
 * versions in the wild are not something this repo controls — being liberal
 * here costs nothing and turning a valid run away costs someone their
 * benchmark.
 *
 * Only shape 1 carries a transport checksum. That matters less than it sounds:
 * a truncated or corrupted payload still fails to inflate under 1 and 2 alike,
 * which was the failure that prompted all of this, and the bot seals the
 * measurements itself further down — so what lands in the repo is equally
 * protected however it arrived.
 */
const readSubmission = async () => {
    const token = findToken(body);

    if (token) {
        try {
            return await decodeToken(token);
        } catch (error) {
            bail(error.message);
        }
    }

    const payload = findFencedPayload(body);

    if (payload) {
        try {
            return await decodePayload(payload);
        } catch (error) {
            bail(error.message);
        }
    }

    const fence = body.match(/```json\s*([\s\S]*?)```/i);

    if (fence) {
        try {
            return JSON.parse(fence[1].trim());
        } catch (e) {
            bail(`The results JSON could not be parsed: ${e.message}`);
        }
    }

    if (looksLikeToken(body)) {
        bail('Your submission token looks cut short — the issue holds the start of one but not a complete token. Copy the whole thing from BenchKit and paste it here, or open a fresh issue with **Submit result**.');
    }

    bail('Could not find a submission token in the issue. Open your run in BenchKit, click **Submit result**, and use the link it gives you.');
};

const parsed = await readSubmission();

// Accept either the bare run document or a { run } wrapper.
let run = parsed;
if (parsed && typeof parsed === 'object' && parsed.run && !parsed.id) run = parsed.run;

if (!run || typeof run !== 'object') bail('The submission is not an object.');

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

const doc = await buildDocument(run, {
    github: author,
    submittedAt: (run.created_at ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
    verified: false,
});

const { errors } = await validateSubmission(doc, path);
if (errors.length) {
    bail('The submission didn\'t pass validation:', ...errors.map(e => `- ${e}`));
}

mkdirSync(dirname(path), { recursive: true });
writeFileSync(path, `${JSON.stringify(doc, null, 2)}\n`);

console.log(`Wrote ${path} for @${author}`);
setOutput('valid', 'true');
setOutput('id', run.id);
setOutput('path', path);
