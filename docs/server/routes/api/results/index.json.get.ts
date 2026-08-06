import { runIndex } from '../../../utils/runs'

/**
 * Every community run, summary fields only — the file the gallery loads.
 *
 * Prerendered to a static /api/results/index.json by modules/results-api.ts,
 * so this handler only ever executes at build time on a static deploy.
 */
export default eventHandler(async (event) => {
    const runs = await runIndex()

    setHeader(event, 'Content-Type', 'application/json; charset=utf-8')

    return { schema_version: 1, count: runs.length, runs }
})
