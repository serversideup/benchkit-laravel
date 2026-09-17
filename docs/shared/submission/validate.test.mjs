// Exercises the generator/load_mode rules with node's built-in runner, because
// the validator deliberately has no npm dependencies:
//
//   node --test docs/shared/submission/validate.test.mjs
//
// The fixture goes through buildDocument() first — exactly what the bot does —
// so a passing case here is a document the bot would seal and merge.

import { test } from 'node:test'
import assert from 'node:assert/strict'

import { buildDocument } from './run-document.mjs'
import { SCHEMA_VERSION, validateSubmission } from './validate.mjs'

const baseRun = () => ({
    schema_version: SCHEMA_VERSION,
    id: '20260821-101500-ab12',
    created_at: '2026-08-21T10:15:00+00:00',
    meta: { label: 'Test run', provider: 'Hetzner' },
    settings_preset: 'quick',
    stages_completed: ['http'],
    environment: {
        server: { cpu_model: 'AMD EPYC 7502P', cpu_cores: 4, os: 'Ubuntu 24.04', ram: '8192 MB' },
        php: { php_version: '8.5.0', php_variation: 'frankenphp' },
        php_environment_source: 'web',
        laravel: { environment: { laravel_version: '13.0.0', debug_mode: false } },
        database: { driver: 'sqlite', durability: { journal_mode: 'wal' } }
    },
    benchmarks: {
        http: {
            mode: 'loopback',
            tls: false,
            duration_seconds: 10,
            connections: 50,
            io_ms: 100,
            workers: 16,
            oversubscribed: true,
            pool_limited: false,
            routes: {
                static: {
                    path: '/bench/static',
                    throughput: { requests_per_second: 1234.5, concurrency: 42, success_rate: 1, saturated: true },
                    latency: { p50_ms: 10, p95_ms: 25, p99_ms: 40, corrected: true },
                    curve: [
                        { concurrency: 1, requests_per_second: 90.1 },
                        { concurrency: 42, requests_per_second: 1234.5 }
                    ]
                }
            }
        },
        php: null
    }
})

const validate = async (run) => {
    const document = await buildDocument(run, { github: 'someone', submittedAt: '2026-08-21' })

    return { document, result: await validateSubmission(document, null) }
}

test('a run without a generator block back-fills to self and validates clean', async () => {
    const { document, result } = await validate(baseRun())

    assert.deepEqual(result.errors, [])
    assert.equal(document.load_mode, 'self')
})

test('a well-formed external run validates clean and indexes as external', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'external', rtt_ms: 1.8, oha_version: '1.4.5' }
    run.benchmarks.http.generator_bound = false

    const { document, result } = await validate(run)

    assert.deepEqual(result.errors, [])
    assert.equal(document.load_mode, 'external')
})

test('an unknown generator mode is rejected — it is the partition axis', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'laptop' }

    const { result } = await validate(run)

    assert.ok(result.errors.some(e => e.includes('generator.mode')))
})

test('a generator-bound run is rejected with the network-path explanation', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'external', rtt_ms: 61, oha_version: '1.4.5' }
    run.benchmarks.http.generator_bound = true

    const { result } = await validate(run)

    assert.ok(result.errors.some(e => e.includes('path to the server rather than the server')))
})

test('the generator machine\'s IP and hostname are refused by name', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'external', rtt_ms: 1.8, source_ip: '198.51.100.7', host: 'jays-macbook.local' }

    const { result } = await validate(run)

    assert.ok(result.errors.some(e => e.includes('generator.source_ip')))
    assert.ok(result.errors.some(e => e.includes('generator.host')))
})

test('an external run that does not say whether the generator kept up warns', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'external', rtt_ms: 1.8 }

    const { result } = await validate(run)

    assert.deepEqual(result.errors, [])
    assert.ok(result.warnings.some(w => w.includes('generator kept up')))
})

test('a hand-edited load_mode is caught by index recomputation', async () => {
    const { document } = await validate(baseRun())
    document.load_mode = 'external'

    const result = await validateSubmission(document, null)

    assert.ok(result.errors.some(e => e.startsWith('load_mode must be')))
})

test('editing generator.mode after sealing breaks the integrity digest', async () => {
    const run = baseRun()
    run.benchmarks.http.generator = { mode: 'self', rtt_ms: 0.4 }

    const { document } = await validate(run)
    document.run.benchmarks.http.generator.mode = 'external'
    // Keep the index consistent with the edit, so the only thing left to
    // notice the change is the seal — which is the property under test.
    document.load_mode = 'external'

    const result = await validateSubmission(document, null)

    assert.ok(result.errors.some(e => e.includes('integrity.digest')))
})
