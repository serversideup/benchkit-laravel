import { addPrerenderRoutes, createResolver, defineNuxtModule } from '@nuxt/kit'
import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Publishes the community runs in docs/data/runs as a small static API:
 *
 *   /api/results/index.json     every run, summary fields only (~400 B each)
 *   /api/results/<run id>.json  one run, in full              (~3 KB each)
 *
 * The gallery loads the index; a result page loads one record. Neither gets
 * heavier because the other did — which is the entire point of the split.
 *
 * Why not a Nuxt Content collection? Because nothing here is a query. The
 * gallery filters, sorts, and facets with plain Array methods over data it
 * already holds, and a result page looks a run up by id — a file read wearing
 * a SQL costume. A collection would compile the runs into SQLite, ship an
 * 856 KB WASM engine to the browser, and publish a dump of the whole table
 * (each run stored twice: once as a column, again inside Content's `meta`) to
 * serve an array we then filter in JavaScript. That's the full cost of a query
 * engine for none of the benefit, and it grows with every submission. Content
 * still owns docs/content, where its markdown pipeline earns its keep.
 *
 * The routes are ordinary Nitro handlers (server/routes/api/results) so they
 * work identically in dev, during prerender, and on a real server. This module
 * only mounts the data and lists what to prerender — on a static build the
 * prerenderer turns each route into a plain file, and the handlers never run
 * again.
 */
export default defineNuxtModule({
    meta: {
        name: 'results-api',
        configKey: 'resultsApi'
    },
    setup(_options, nuxt) {
        const { resolve } = createResolver(import.meta.url)
        const dataDir = resolve(nuxt.options.rootDir, 'data/runs')

        // Runs are sharded into month directories (runs/2026-08/<id>.json).
        function runFiles(dir: string): string[] {
            try {
                return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
                    const path = join(dir, entry.name)
                    if (entry.isDirectory()) return runFiles(path)
                    return entry.name.endsWith('.json') ? [path] : []
                })
            } catch {
                // No runs submitted yet — the gallery renders its empty state.
                return []
            }
        }

        // Deliberately shallow. The real gate is the validate-run-submission
        // action, which blocks the PR; this only catches a file that would
        // render as a blank card, and it throws rather than warns so a broken
        // run fails the build instead of shipping silently.
        function runIds(): string[] {
            return runFiles(dataDir).map((file) => {
                const doc = JSON.parse(readFileSync(file, 'utf8'))

                const problem
                    = typeof doc?.run_id !== 'string'
                        ? 'no run_id'
                        : typeof doc?.run !== 'object' || doc.run === null
                            ? 'no run object'
                            : typeof doc?.submitted_at !== 'string'
                                ? 'no submitted_at'
                                : null

                if (problem) {
                    throw new Error(`${file} is not a usable run document (${problem}). Run: node .github/scripts/validate-run-submission.mjs`)
                }

                return doc.run_id as string
            })
        }

        const ids = runIds()
        console.log(`Publishing ${ids.length} community result${ids.length === 1 ? '' : 's'} to /api/results`)

        // Mounted as a server asset rather than read off disk, so the handlers
        // don't need to know where the project root is. Via the hook, not
        // nuxt.options.nitro — Nitro's config is resolved before a module's
        // direct mutations land.
        nuxt.hook('nitro:config', (nitroConfig) => {
            nitroConfig.serverAssets ||= []
            nitroConfig.serverAssets.push({ baseName: 'runs', dir: dataDir })
        })

        // Both the JSON and the pages. Link crawling finds neither: the JSON is
        // fetched, not linked, and the gallery pages its grid so only the first
        // screen of runs is ever in the HTML. The directory is the only
        // complete source.
        addPrerenderRoutes([
            '/api/results/index.json',
            ...ids.map(id => `/api/results/${id}.json`),
            ...ids.map(id => `/results/${id}`)
        ])
    }
})
