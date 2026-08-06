/**
 * URL of a file published by docs/modules/results-api.ts.
 *
 * Routed through app.baseURL because the site is served from a sub-path in
 * production (/open-source/benchkit) but from the root in dev — a hard-coded
 * "/api/results/..." works locally and 404s once deployed.
 */
export function resultsApi(file: string): string {
    const base = useRuntimeConfig().app.baseURL || '/'

    return `${base.replace(/\/+$/, '')}/api/results/${file}`
}
