import { defineContentConfig, defineCollection, z } from '@nuxt/content'

// Content owns the docs — markdown, prose, navigation, TOC — which is what its
// pipeline is for. Community benchmark runs are deliberately NOT a collection:
// they're a dataset, queried by nothing (the gallery filters and sorts a plain
// array, the detail page looks one up by id), and a collection would compile
// them into SQLite and ship a dump of every run to the browser. They're
// published as static JSON instead — see docs/modules/results-api.ts.

export default defineContentConfig({
    collections: {
        landing: defineCollection({
            type: 'page',
            source: 'index.md'
        }),
        docs: defineCollection({
            type: 'page',
            source: {
                include: '**',
                exclude: ['index.md']
            },
            schema: z.object({
                redirect: z.string().optional(),
                links: z.array(z.object({
                    label: z.string(),
                    icon: z.string(),
                    to: z.string(),
                    target: z.string().optional()
                })).optional()
            })
        })
    }
})
