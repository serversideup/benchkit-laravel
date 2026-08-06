import { readRun } from '../../../utils/runs'

/**
 * One community run, in full — the file a result page loads.
 *
 * Prerendered to a static /api/results/<run id>.json by
 * modules/results-api.ts, so this handler only ever executes at build time on
 * a static deploy. The response is the reviewed source file verbatim: nothing
 * transforms between what a maintainer approved and what the site serves.
 */
export default eventHandler(async (event) => {
    const param = getRouterParams(event)['id.json']

    if (!param?.endsWith('.json')) {
        throw createError({ statusCode: 404, statusMessage: 'Result not found', fatal: true })
    }

    const run = await readRun(param.slice(0, -'.json'.length))

    if (!run) {
        throw createError({ statusCode: 404, statusMessage: 'Result not found', fatal: true })
    }

    setHeader(event, 'Content-Type', 'application/json; charset=utf-8')

    return run
})
