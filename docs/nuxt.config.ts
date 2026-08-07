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

    compatibilityDate: '2024-07-11',

    nitro: {
        prerender: {
            routes: [
                '/'
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
        domain: 'https://serversideup.net/open-source/benchkit/',
        title: 'BenchKit — Understand true Laravel performance',
        description: 'A self-hostable benchmarking playground that measures real-world Laravel performance across hardware and PHP images.',
        full: {
            title: 'BenchKit - Full Documentation',
            description: 'A self-hostable benchmarking playground that measures real-world Laravel performance across hardware and PHP images.'
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
    }
})
