import { defineContentConfig, defineCollection, z } from '@nuxt/content'
import { asSitemapCollection } from '@nuxtjs/sitemap/content'

// Content owns the docs — markdown, prose, navigation, TOC — which is what its
// pipeline is for. Community benchmark runs are deliberately NOT a collection:
// they're a dataset, queried by nothing (the gallery filters and sorts a plain
// array, the detail page looks one up by id), and a collection would compile
// them into SQLite and ship a dump of every run to the browser. They're
// published as static JSON instead — see docs/modules/results-api.ts.

export default defineContentConfig({
    collections: {
        // The home page is not a collection: it's built from components in
        // app/components/home/ and reads live run data, so there is no prose
        // for the content pipeline to own.
        // asSitemapCollection adds the sitemap fields to the collection schema,
        // which is what lets @nuxtjs/sitemap query these pages. Without it that
        // source returns nothing and every docs URL in the sitemap comes from
        // crawling prerendered links instead, which emits them with the base
        // path already applied and then prefixes it a second time.
        docs: defineCollection(asSitemapCollection({
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
        }))
    }
})
