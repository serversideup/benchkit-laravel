/**
 * URL of a file in docs/public.
 *
 * Same reason as resultsApi(): the site is served from a sub-path in production
 * (/open-source/benchkit) but from the root in dev. Nuxt prefixes the base URL
 * onto build assets and <NuxtLink> targets on its own, but not onto paths handed
 * to <img>, <NuxtImg>, or useHead(), so a hard-coded "/images/..." resolves
 * against serversideup.net itself once deployed, where nothing serves it.
 */
export function publicAsset(path: string): string {
    const base = useRuntimeConfig().app.baseURL || '/'

    return `${base.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`
}
