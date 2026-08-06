/**
 * Reads the community runs in docs/data/runs.
 *
 * The directory is mounted as a Nitro server asset by modules/results-api.ts,
 * so these run through useStorage rather than the filesystem — which keeps the
 * routes working the same way in dev, in a prerender, and on a real server.
 */

const ID_RE = /^[0-9]{8}-[0-9]{6}-[a-z0-9]+$/

/** Runs are sharded by month; both parts of the path come from the id. */
const keyFor = (id: string) => `${id.slice(0, 4)}-${id.slice(4, 6)}:${id}.json`

const storage = () => useStorage('assets:runs')

export async function readRun(id: string): Promise<Record<string, unknown> | null> {
    // Guards the storage key against traversal before it is ever built.
    if (!ID_RE.test(id)) return null

    return await storage().getItem(keyFor(id)) as Record<string, unknown> | null
}

/**
 * Every run's summary fields, newest first. The `run` document is dropped —
 * that's the whole point of the index, and why listing runs doesn't get
 * heavier as each run's benchmark detail grows.
 */
export async function runIndex(): Promise<Array<Record<string, unknown>>> {
    const keys = (await storage().getKeys()).filter(key => key.endsWith('.json'))

    const runs = await Promise.all(keys.map(async (key) => {
        const doc = await storage().getItem(key) as Record<string, unknown> | null

        if (!doc || typeof doc.run_id !== 'string') return null

        const { run: _run, ...summary } = doc

        return summary
    }))

    // id as the tiebreak so the file is byte-stable between builds and shows
    // up in a diff only when the runs themselves change.
    return runs
        .filter((run): run is Record<string, unknown> => run !== null)
        .sort((a, b) =>
            String(b.submitted_at).localeCompare(String(a.submitted_at))
            || String(a.run_id).localeCompare(String(b.run_id)))
}
