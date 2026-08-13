// Validates community result submissions (docs/data/runs/**/*.json) without
// any npm dependencies, so it runs on a bare Node runner and, unchanged, in a
// browser tab on the site's own /results/submit page. This is the gate: a run
// that fails here never merges, so it's the strictest check in the chain —
// free-text sanitization, plausibility bounds, integrity, and index/run
// consistency, none of which a type schema alone can express.
//
// The filesystem and CLI half lives in .github/scripts/validate-run-submission.mjs.

import { CURRENCIES, findPrivacyLeaks, indexFields, measurementDigest, runsPathFor } from './run-document.mjs'

// Keep in step with AssembleResultsDocument::SCHEMA_VERSION in the app.
// SchemaVersionTest asserts the two match, so this is checked rather than
// remembered.
export const SCHEMA_VERSION = 4

/**
 * Why each superseded version is rejected rather than warned about. A bump
 * here always means a measurement changed, so its runs cannot be read on the
 * same axis as current ones — and a gallery that quietly mixes them is worse
 * than one that is missing a row.
 */
const SUPERSEDED_SCHEMAS = {
    1: 'CRUD subjects rebuilt their own state inside the timed body, so delete reported about 2.4x its real cost',
    2: 'create and update timed PHP datetime work that read and delete did not, and read measured one query returning 100 rows against the other three running 100 statements',
    3: 'warmup revolutions ran each subject body without rebuilding its fixture, so delete measured 100 statements that matched no rows and reported roughly half its real cost'
}

const ID_RE = /^[0-9]{8}-[0-9]{6}-[a-z0-9]+$/
const GITHUB_USER_RE = /^[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}$/i
const KNOWN_VARIATIONS = ['frankenphp', 'fpm-nginx', 'fpm-apache']
const KNOWN_SERVERS = ['php-fpm', 'frankenphp', 'swoole', 'roadrunner', 'mod_php', 'cli-server', 'litespeed']

/**
 * Which web server each image variation should be answering from. php_variation
 * is a build argument — self-reported, and the thing the gallery filters and
 * sorts on — while front_end is measured from the serving process. This is what
 * makes the claim checkable.
 */
const FRONT_END_FOR_VARIATION = {
    'fpm-nginx': 'nginx',
    'fpm-apache': 'apache',
    'frankenphp': 'frankenphp'
}
const MAX_TEXT = 80
const MAX_RPS = 5_000_000
const MAX_MS = 600_000

/**
 * Pure validation of one parsed document. filepath is used only for the
 * id-matches-path check (pass null to skip it, as the site does — a token
 * pasted into /results/submit has no file yet).
 *
 * @returns {Promise<{errors: string[], warnings: string[]}>}
 */
export async function validateSubmission(doc, filepath = null) {
    const errors = []
    const warnings = []
    const err = m => errors.push(m)
    const warn = m => warnings.push(m)

    const isText = (v, label, { max = MAX_TEXT, required = true } = {}) => {
        if (v == null || v === '') {
            if (required) err(`${label} is required`)
            return
        }
        if (typeof v !== 'string') return err(`${label} must be a string`)
        if (v.length > max) err(`${label} is too long (${v.length} > ${max})`)
        // Control characters are exactly what's being looked for here: they
        // would corrupt the rendered gallery and have no place in a host name.
        // eslint-disable-next-line no-control-regex
        if (/[\x00-\x1F\x7F]/.test(v)) err(`${label} contains control characters`)
        if (/<\s*script|<\/?[a-z][\s\S]*>/i.test(v)) err(`${label} contains HTML/script markup`)
    }

    const isNum = (v, label, { min = -Infinity, max = Infinity, required = true } = {}) => {
        if (v == null) {
            if (required) err(`${label} is required`)
            return
        }
        if (typeof v !== 'number' || Number.isNaN(v)) return err(`${label} must be a number`)
        if (v < min || v > max) err(`${label} out of range (${v}, expected ${min}..${max})`)
    }

    if (typeof doc !== 'object' || doc === null || Array.isArray(doc)) {
        return { errors: ['Top level must be an object of index fields plus a `run` object'], warnings }
    }
    const run = doc.run
    if (typeof run !== 'object' || run === null) {
        return { errors: ['Missing `run` object'], warnings }
    }

    // ---- submitter (github comes from the authenticated issue author) ----
    const gh = doc.github
    if (gh != null && typeof gh !== 'string') err('github must be a string')
    if (typeof gh === 'string' && gh !== '' && !GITHUB_USER_RE.test(gh)) warn(`github "${gh}" doesn't look like a GitHub username`)
    if (doc.verified != null && typeof doc.verified !== 'boolean') err('verified must be a boolean')
    // The Maintainer badge isn't self-serve — flag for the reviewer, don't block.
    if (doc.verified === true) warn('verified is true — the Maintainer badge should only be set on team-added runs')
    if (typeof doc.submitted_at !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(doc.submitted_at)) err('submitted_at must be a YYYY-MM-DD date')
    // The issue a result arrived in, added by the bot. Optional, because runs
    // filed before this existed are still perfectly good runs.
    if (doc.issue != null && (!Number.isInteger(doc.issue) || doc.issue < 1)) err('issue must be a positive whole number')

    // ---- identity ----
    if (typeof run.id !== 'string' || !ID_RE.test(run.id)) {
        err(`run.id "${run.id}" is not a valid run id (expected e.g. 20260805-152622-l9ft)`)
    } else if (filepath && filepath.replaceAll('\\', '/') !== runsPathFor(run.id)) {
        err(`file must live at ${runsPathFor(run.id)}, got ${filepath}`)
    }
    if (typeof run.schema_version !== 'number') {
        err('run.schema_version is required and must be a number')
    } else if (SUPERSEDED_SCHEMAS[run.schema_version]) {
        err(`schema_version ${run.schema_version} runs are not comparable with current results — ${SUPERSEDED_SCHEMAS[run.schema_version]}. Please re-run the benchmark and submit again.`)
    } else if (run.schema_version !== SCHEMA_VERSION) {
        warn(`schema_version is ${run.schema_version} (validator knows version ${SCHEMA_VERSION})`)
    }
    if (typeof run.created_at !== 'string' || Number.isNaN(Date.parse(run.created_at))) err('run.created_at must be an ISO date string')

    // ---- meta ----
    const meta = run.meta ?? {}
    isText(meta.label, 'meta.label')
    for (const key of ['provider', 'plan', 'datacenter']) {
        if (meta[key] != null) isText(meta[key], `meta.${key}`, { max: 60, required: false })
    }
    // Cost is the one field the gallery does arithmetic on, so it's structured
    // or absent — never free text. Currency is stored as the submitter is
    // billed and never converted: the gallery ranks value within one currency
    // at a time rather than applying a rate nobody can audit.
    if (meta.cost != null) {
        if (typeof meta.cost !== 'object' || Array.isArray(meta.cost)) {
            err('meta.cost must be an object { amount, currency, period } or null')
        } else {
            isNum(meta.cost.amount, 'meta.cost.amount', { min: 0, max: 1_000_000 })
            if (!CURRENCIES.includes(meta.cost.currency)) err(`meta.cost.currency "${meta.cost.currency}" is not a currency BenchKit records`)
            if (meta.cost.period !== 'monthly') err('meta.cost.period must be "monthly" — every cost is normalized to a month')
        }
    }

    // ---- environment ----
    const env = run.environment ?? {}
    const server = env.server ?? {}
    isText(server.cpu_model, 'environment.server.cpu_model', { max: 120 })
    if (server.cpu_cores == null || !['string', 'number'].includes(typeof server.cpu_cores)) err('environment.server.cpu_cores is required')
    isText(server.os, 'environment.server.os', { max: 120 })
    isText(server.ram, 'environment.server.ram', { max: 40 })

    const php = env.php ?? {}
    isText(php.php_version, 'environment.php.php_version', { max: 20 })
    isText(php.php_variation, 'environment.php.php_variation', { max: 40 })
    if (php.php_variation && !KNOWN_VARIATIONS.includes(php.php_variation)) warn(`unknown php_variation "${php.php_variation}"`)
    // Not an error — a real run on a real box, and worth having. But OPcache off
    // depresses every number so far below a production configuration that it
    // shouldn't sit unlabelled next to runs that had it on.
    if (php.op_cache != null && String(php.op_cache) !== '1') {
        warn('OPcache was disabled for this run, so its numbers are far below a production configuration and are not comparable with the rest of the gallery')
    }

    // Which process the php block above describes. A run assembled without the
    // HTTP stage reports the CLI's opcache, JIT, and memory limit — real
    // settings, but not the ones that served anything, so they cannot be read
    // as the configuration behind a throughput number.
    if (env.php_environment_source != null && !['web', 'cli'].includes(env.php_environment_source)) {
        err(`environment.php_environment_source "${env.php_environment_source}" is not one of web, cli`)
    }
    if (env.php_environment_source === 'cli') {
        warn('the PHP settings in this run were read from the command-line process that assembled it, not from the web server that served the load test, so opcache and memory limit describe a different SAPI')
    }

    if (env.laravel?.environment?.laravel_version == null) err('environment.laravel.environment.laravel_version is required')

    // Debug mode collects stack-trace data on every request. The run is real,
    // but it measures a development configuration, and the gap is wide enough
    // that it cannot sit unlabelled beside the rest of the gallery.
    if (env.laravel?.environment?.debug_mode === true) {
        warn('this run was measured with APP_DEBUG on, so its numbers describe a development configuration rather than a deployed one')
    }
    if (env.laravel?.environment?.debug_mode != null && typeof env.laravel.environment.debug_mode !== 'boolean') {
        err('environment.laravel.environment.debug_mode must be a boolean')
    }

    // ---- benchmarks ----
    const benchmarks = run.benchmarks ?? {}
    if (typeof benchmarks !== 'object') err('run.benchmarks must be an object')

    const http = benchmarks.http
    if (http != null) {
        const routes = http.routes ?? {}
        const routeKeys = Object.keys(routes)
        if (routeKeys.length === 0) err('benchmarks.http.routes has no routes')
        for (const key of routeKeys) {
            const r = routes[key]
            if (r == null) continue
            isNum(r.requests_per_second, `http.routes.${key}.requests_per_second`, { min: 0, max: MAX_RPS })
            for (const p of ['p50_ms', 'p95_ms', 'p99_ms']) isNum(r[p], `http.routes.${key}.${p}`, { min: 0, max: MAX_MS })
            if (r.success_rate != null) isNum(r.success_rate, `http.routes.${key}.success_rate`, { min: 0, max: 1 })
            // Wall time the load generator observed, against the duration the
            // run asked for below. A throughput figure is a count over a time,
            // and this is the time it was actually divided by.
            if (r.elapsed_seconds != null) isNum(r.elapsed_seconds, `http.routes.${key}.elapsed_seconds`, { min: 0, max: 3600 })
        }
        // Both container-internal ports report mode "loopback", so without this
        // a run paying for a TLS handshake and per-request encryption looks
        // identical to a plaintext one.
        if (http.tls != null && typeof http.tls !== 'boolean') err('http.tls must be a boolean')
        if (http.workers != null) isNum(http.workers, 'http.workers', { min: 1, max: 100_000 })
        if (http.pool_limited != null && typeof http.pool_limited !== 'boolean') err('http.pool_limited must be a boolean')
        if (http.oversubscribed != null && typeof http.oversubscribed !== 'boolean') err('http.oversubscribed must be a boolean')
        // Not fatal — the run is real, it just isn't a framework comparison.
        // Surfacing it in review is what keeps the gallery interpretable.
    }

    const phpBench = benchmarks.php
    if (phpBench?.headline != null) {
        // The four tiles are rendered on one bar scale, so they have to have
        // measured the same unit of work. `statements` is what makes that
        // checkable here rather than taken on trust from the run's own version.
        const statements = new Set()

        for (const op of ['create', 'read', 'update', 'delete']) {
            const cell = phpBench.headline[op]
            if (cell == null) continue
            isNum(cell.milliseconds, `php.headline.${op}.milliseconds`, { min: 0, max: MAX_MS })
            isNum(cell.statements, `php.headline.${op}.statements`, { min: 1, max: 1_000_000 })
            statements.add(cell.statements)
            for (const key of ['best_ms', 'worst_ms']) {
                if (cell[key] != null) isNum(cell[key], `php.headline.${op}.${key}`, { min: 0, max: MAX_MS })
            }
            if (cell.rstdev != null) isNum(cell.rstdev, `php.headline.${op}.rstdev`, { min: 0, max: 1000 })
            if (cell.iterations != null) isNum(cell.iterations, `php.headline.${op}.iterations`, { min: 1, max: 100_000 })
            // A mean over one iteration has no spread to report and no way to
            // be checked. Not fatal — it is a real measurement — but it should
            // not sit unlabelled beside runs that were measured repeatedly.
            if (cell.iterations === 1) warn(`php.headline.${op} is a single iteration, so its mean has no spread behind it`)
            if (cell.rstdev > 10) warn(`php.headline.${op} varied by ±${cell.rstdev}% across iterations, so its mean is an estimate rather than a measurement`)
        }

        if (statements.size > 1) {
            err(`php.headline operations report different statement counts (${[...statements].join(', ')}) — the four CRUD tiles share a scale and must measure the same unit of work`)
        }
    }

    const cf = benchmarks.cfspeedtest
    if (cf != null) {
        // On home hardware these are the submitter's ISP and nearest city, and
        // the speeds are what the community is actually comparing.
        for (const key of ['asn', 'colo']) {
            if (cf[key] != null) err(`cfspeedtest.${key} is not published — remove it`)
        }
        for (const [k, bounds] of [['latency_ms', { min: 0, max: MAX_MS }], ['download_mbps', { min: 0, max: 1_000_000 }], ['upload_mbps', { min: 0, max: 1_000_000 }]]) {
            if (cf[k] != null) isNum(cf[k], `cfspeedtest.${k}`, { ...bounds, required: false })
        }
    }

    const gb = benchmarks.geekbench
    if (gb != null) {
        isNum(gb.single, 'geekbench.single', { min: 0, max: 1_000_000 })
        isNum(gb.multi, 'geekbench.multi', { min: 0, max: 1_000_000 })
        if (gb.url != null && !/^https:\/\/[^\s]+$/.test(String(gb.url))) err('geekbench.url must be an https URL')
    }

    if (benchmarks.disk != null) {
        if (!Array.isArray(benchmarks.disk)) err('benchmarks.disk must be an array')
        else for (const row of benchmarks.disk) {
            isText(row?.bs, 'disk[].bs', { max: 12 })
            for (const k of ['speed_r', 'speed_w', 'speed_rw']) {
                if (row?.[k] != null) isNum(row[k], `disk[].${k}`, { min: 0, max: 10_000_000, required: false })
            }
        }
    }

    if (http == null && phpBench == null) warn('submission has neither HTTP nor PHP benchmarks — it will render nearly empty')

    // ---- environment extras ----
    // Configuration that explains the numbers. Each is bounded here as well as
    // at submission time, because a PR can be hand-edited and this is the only
    // check that runs on it.
    if (php.php_server_api != null && !/^[A-Za-z0-9+-]{1,30}$/.test(String(php.php_server_api))) {
        err('environment.php.php_server_api has an unexpected shape')
    }

    if (php.ini != null) {
        if (typeof php.ini !== 'object' || Array.isArray(php.ini)) {
            err('environment.php.ini must be an object of setting => value')
        } else {
            // opcache.preload is a filesystem path and must never appear; the
            // app publishes opcache.preload_enabled instead.
            if ('opcache.preload' in php.ini) err('environment.php.ini must not include opcache.preload — publish opcache.preload_enabled instead')
            for (const [key, value] of Object.entries(php.ini)) {
                if (!/^[a-z_]+(?:\.[a-z_]+)*$/.test(key)) err(`environment.php.ini key "${key}" has an unexpected shape`)
                if (typeof value === 'string') isText(value, `environment.php.ini["${key}"]`, { max: 40, required: false })
                else if (typeof value !== 'number' && typeof value !== 'boolean') err(`environment.php.ini["${key}"] must be a string, number, or boolean`)
            }
        }
    }

    // How the application was served. server/mode/workers are normalized so an
    // FPM pool size and a FrankenPHP thread count land in the same column;
    // `settings` is whatever that server exposes, so it is shape-checked rather
    // than enumerated — the point of it is to carry runtimes this file has
    // never heard of.
    if (php.runtime != null) {
        if (php.runtime.server != null && !KNOWN_SERVERS.includes(php.runtime.server)) {
            warn(`unknown runtime server "${php.runtime.server}"`)
        }
        if (php.runtime.mode != null && !['worker', 'process-per-request'].includes(php.runtime.mode)) {
            err(`environment.php.runtime.mode "${php.runtime.mode}" is not one of worker, process-per-request`)
        }
        if (php.runtime.workers != null) isNum(php.runtime.workers, 'environment.php.runtime.workers', { min: 1, max: 100_000 })
        // A count with no unit cannot be compared with another count. Twenty
        // FPM children and eight FrankenPHP threads are both "workers".
        if (php.runtime.workers != null && php.runtime.workers_source == null) {
            warn('this run reports a worker count without saying what it counts, so it cannot be compared with runs on other servers')
        }
        if (php.runtime.front_end != null) isText(php.runtime.front_end, 'environment.php.runtime.front_end', { max: 30, required: false })
        if (php.runtime.settings != null) {
            if (typeof php.runtime.settings !== 'object' || Array.isArray(php.runtime.settings)) {
                err('environment.php.runtime.settings must be an object of setting => value')
            } else {
                for (const [key, value] of Object.entries(php.runtime.settings)) {
                    if (!/^[a-z_]+(?:\.[a-z_]+)*$/.test(key)) err(`environment.php.runtime.settings key "${key}" has an unexpected shape`)
                    isText(String(value), `environment.php.runtime.settings["${key}"]`, { max: 30, required: false })
                }
            }
        }

        // The build arg says which image this claims to be; SERVER_SOFTWARE says
        // what actually answered. php_variation is self-reported and the gallery
        // filters on it, so this is the only check there is that it is true.
        // A warning, not an error — a custom build can legitimately disagree.
        const claimed = FRONT_END_FOR_VARIATION[php.php_variation]
        if (claimed && php.runtime.front_end && claimed !== php.runtime.front_end) {
            warn(`this run reports php_variation "${php.php_variation}" but was served by ${php.runtime.front_end}`)
        }
    }

    // A registry-style tag would carry someone's org name into a public file.
    if (env.build_version != null && !/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/.test(String(env.build_version))) {
        err('environment.build_version must be a plain version tag (no registry path)')
    }

    if (run.settings_preset != null && !['quick', 'full', 'custom'].includes(run.settings_preset)) {
        warn(`unknown settings_preset "${run.settings_preset}"`)
    }

    if (run.stages_completed != null && !Array.isArray(run.stages_completed)) {
        err('stages_completed must be an array')
    }

    if (phpBench?.subjects != null) {
        if (!Array.isArray(phpBench.subjects)) err('php.subjects must be an array')
        else for (const row of phpBench.subjects.slice(0, 100)) {
            if (!/^[A-Za-z0-9_]{1,60}$/.test(row?.benchmark ?? '')) err('php.subjects[].benchmark has an unexpected shape')
            if (!/^[A-Za-z0-9_]{1,60}$/.test(row?.subject ?? '')) err('php.subjects[].subject has an unexpected shape')
            isNum(row?.mean_us, 'php.subjects[].mean_us', { min: 0, max: 1e12 })
            for (const key of ['best_us', 'worst_us', 'stdev_us']) {
                if (row?.[key] != null) isNum(row[key], `php.subjects[].${key}`, { min: 0, max: 1e12 })
            }
            if (row?.rstdev != null) isNum(row.rstdev, 'php.subjects[].rstdev', { min: 0, max: 1000 })
            for (const key of ['revolutions', 'iterations']) {
                if (row?.[key] != null) isNum(row[key], `php.subjects[].${key}`, { min: 1, max: 1_000_000 })
            }
        }
    }

    // The settings that decide whether a commit waits for durable storage.
    // They move the CRUD write numbers by orders of magnitude, so a run that
    // reports them can be read and one that doesn't can only be guessed at.
    const database = env.database
    if (database != null) {
        if (typeof database !== 'object' || Array.isArray(database)) {
            err('environment.database must be an object')
        } else {
            if (database.driver != null) isText(database.driver, 'environment.database.driver', { max: 20 })
            if (database.version != null) isText(database.version, 'environment.database.version', { max: 40 })
            if (database.filesystem != null) isText(database.filesystem, 'environment.database.filesystem', { max: 20 })
            if (database.durability != null) {
                if (typeof database.durability !== 'object' || Array.isArray(database.durability)) {
                    err('environment.database.durability must be an object of setting => value')
                } else {
                    for (const [key, value] of Object.entries(database.durability)) {
                        if (!/^[a-z_]{1,40}$/.test(key)) err(`environment.database.durability key "${key}" has an unexpected shape`)
                        if (value != null) isText(String(value), `environment.database.durability["${key}"]`, { max: 20 })
                    }
                }
            }
        }
    } else if (phpBench != null) {
        warn('this run has database benchmarks but does not report the database durability settings behind them, so its write numbers cannot be interpreted')
    }

    // ---- privacy ----
    // Last line of defence, and the only one that covers fields nobody has
    // thought about yet.
    for (const leak of findPrivacyLeaks(doc)) err(leak)

    // ---- integrity ----
    // The bot seals a run's measurements when it accepts the submission. Any
    // later edit — in the pull request, or committed straight to main — changes
    // the numbers without changing the seal, and that shows up here.
    const integrity = run.integrity
    if (integrity == null || typeof integrity !== 'object' || Array.isArray(integrity)) {
        err('run.integrity is missing — every run the bot accepts is sealed, so a run without a seal did not come through the submission flow')
    } else if (integrity.algorithm !== 'sha256') {
        err(`run.integrity.algorithm "${integrity.algorithm}" is not an algorithm this validator can check`)
    } else if (integrity.digest !== await measurementDigest(run)) {
        err('run.integrity.digest does not match this run\'s measurements — something under environment or benchmarks changed after the submission was accepted. Restore the original values, or re-submit from the app. (meta — label, host, plan, cost — is deliberately outside the seal and safe to correct.)')
    }

    // ---- index fields ----
    // The flat top-level fields are what the gallery filters and sorts on, and
    // they're derived from `run`. Recompute them: a hand-edited PR that tweaks
    // a number up here would show one figure in the list and another on the
    // detail page, and nobody would notice which one was real.
    for (const [key, expected] of Object.entries(indexFields(run))) {
        const actual = doc[key] ?? null
        if (actual !== expected) err(`${key} must be ${JSON.stringify(expected)} to match the run, got ${JSON.stringify(actual)}`)
    }

    return { errors, warnings }
}
