// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    modules: [
        '@nuxt/eslint',
        '@nuxt/image',
        '@nuxt/ui',
        '@nuxt/content',
        '@nuxtjs/plausible',
        '@nuxtjs/sitemap',
        '@vueuse/nuxt',
        'nuxt-og-image',
        'nuxt-llms',
        'nuxt-schema-org',
        './modules/pre-render-raw-routes',
        './modules/results-api'
    ],

    devtools: {
        enabled: true
    },

    css: ['~/assets/css/main.css'],

    // site.url is the ORIGIN only; the /open-source/benchkit sub-path comes
    // from NUXT_APP_BASE_URL (app.baseURL), which nuxt-site-config combines
    // with this to build canonical/sitemap/OG URLs.
    site: {
        url: process.env.NUXT_SITE_URL || 'https://serversideup.net',
        name: process.env.NUXT_SITE_NAME || 'BenchKit — Understand true Laravel performance',
        env: process.env.NUXT_SITE_ENV || 'production'
    },

    content: {
        build: {
            markdown: {
                highlight: {
                    theme: 'github-dark',
                    langs: [
                        'bash',
                        'dockerfile',
                        'yaml',
                        'json',
                        'nginx',
                        'php',
                        'ini',
                        'diff'
                    ]
                },
                toc: {
                    searchDepth: 2
                }
            }
        }
    },

    ui: {
        colorMode: false
    },

    // Pages removed when the docs were consolidated. These are permanent
    // because the BenchKit application itself links to the load test page, and
    // containers already running in the wild will keep doing so indefinitely.
    routeRules: {
        // Was a content page carrying a `redirect` field, which meant /docs
        // rendered an empty page and bounced on the client. As a route rule it
        // is a real 301, and the sitemap drops it the way it drops the others.
        '/docs': { redirect: { to: '/docs/getting-started', statusCode: 301 } },
        '/docs/configuration/default-configurations': { redirect: { to: '/docs/benchmarks', statusCode: 301 } },
        '/docs/benchmarks/web-server-load-test': { redirect: { to: '/docs/benchmarks', statusCode: 301 } },
        '/docs/benchmarks/throughput-vs-latency': { redirect: { to: '/docs/benchmarks/reading-your-results', statusCode: 301 } },
        '/docs/benchmarks/limitations': { redirect: { to: '/docs/benchmarks/reading-your-results', statusCode: 301 } }
    },

    compatibilityDate: '2024-07-11',

    nitro: {
        prerender: {
            routes: [
                '/',
                // Listed rather than left to link crawling: it's a fully
                // client-side tool, so if a link to it ever moved, the route
                // would silently stop being emitted.
                '/results/submit'
            ],
            crawlLinks: true,
            autoSubfolderIndex: false
        }
    },

    eslint: {
        config: {
            stylistic: {
                commaDangle: 'never',
                braceStyle: '1tbs'
            }
        }
    },

    llms: {
        // No trailing slash: nuxt-llms joins this with a leading-slash path, so
        // one here produced "benchkit//llms-full.txt" in the generated index.
        domain: 'https://serversideup.net/open-source/benchkit',
        title: 'BenchKit — Understand true Laravel performance',
        description: 'A free and open source Laravel application that measures how performance changes across hosts, hardware, and PHP configurations. Run it on the server you want to measure, compare runs, and share results with the community.',
        full: {
            title: 'BenchKit - Full Documentation',
            description: 'Complete BenchKit documentation: running it, configuring PHP and the database, the image variations, how the benchmarks work, and how to read the results.'
        },
        sections: [
            {
                title: 'Getting Started',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/getting-started%' }
                ]
            },
            {
                title: 'Configuration',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/configuration%' }
                ]
            },
            {
                title: 'Image Variations',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/image-variations%' }
                ]
            },
            {
                title: 'Benchmarks & Methodology',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/benchmarks%' }
                ]
            },
            {
                title: 'Community Results',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/community-results%' }
                ]
            },
            {
                title: 'FAQ',
                contentCollection: 'docs',
                contentFilters: [
                    { field: 'path', operator: 'LIKE', value: '/docs/faq%' }
                ]
            }
        ]
    },

    // Analytics off unless PLAUSIBLE_ENABLED=true. Points at Server Side Up's
    // self-hosted Plausible; the site domain scopes it to /open-source/benchkit.
    plausible: {
        enabled: process.env.PLAUSIBLE_ENABLED === 'true',
        apiHost: 'https://a.521dimensions.com'
    },

    sitemap: {
        // The prerender crawler reports paths with the base URL already on
        // them, which the sitemap then prefixes again, so the site root came
        // out as /open-source/benchkit/open-source/benchkit. Pages now come
        // from the page files and the content collection, both of which report
        // paths relative to the base.
        excludeAppSources: ['nuxt:prerender']
    }
})
