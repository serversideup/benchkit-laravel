import { defineContentConfig, defineCollection, z } from '@nuxt/content'

// One HTTP route's benchmark numbers (static / json / db_read / io).
const httpRoute = z.object({
    path: z.string().optional(),
    requests_per_second: z.number(),
    success_rate: z.number(),
    p50_ms: z.number(),
    p95_ms: z.number(),
    p99_ms: z.number(),
    total_requests: z.number().optional(),
    status_codes: z.record(z.string(), z.number()).optional()
})

const phpHeadline = z.object({
    milliseconds: z.number(),
    records: z.number().optional(),
    label: z.string().optional()
})

// The public run document, mirroring app/Actions/Results/AssembleResultsDocument.php.
// Submissions send a TRIMMED version (no logs / ini / subjects / settings).
const runDocument = z.object({
    schema_version: z.number(),
    id: z.string(),
    created_at: z.string(),
    meta: z.object({
        label: z.string(),
        provider: z.string().nullable().optional(),
        plan: z.string().nullable().optional(),
        datacenter: z.string().nullable().optional(),
        cost: z.union([z.number(), z.string()]).nullable().optional()
    }),
    environment: z.object({
        server: z.object({
            cpu_model: z.string(),
            cpu_cores: z.union([z.string(), z.number()]),
            cpu_frequency: z.string().optional(),
            os: z.string(),
            ram: z.string()
        }),
        php: z.object({
            php_version: z.string(),
            php_variation: z.string(),
            octane: z.boolean().optional(),
            op_cache: z.union([z.string(), z.boolean()]).optional(),
            memory_limit: z.string().optional()
        }),
        laravel: z.object({
            environment: z.object({
                laravel_version: z.string()
            }),
            drivers: z.record(z.string(), z.any()).optional()
        })
    }),
    benchmarks: z.object({
        http: z.object({
            mode: z.string().optional(),
            duration_seconds: z.number().optional(),
            connections: z.number().optional(),
            io_ms: z.number().optional(),
            routes: z.object({
                static: httpRoute.optional(),
                json: httpRoute.optional(),
                db_read: httpRoute.optional(),
                io: httpRoute.optional()
            })
        }).nullable().optional(),
        php: z.object({
            headline: z.object({
                create: phpHeadline.optional(),
                read: phpHeadline.optional(),
                update: phpHeadline.optional(),
                delete: phpHeadline.optional()
            })
        }).nullable().optional(),
        cfspeedtest: z.object({
            asn: z.union([z.string(), z.number()]).nullable().optional(),
            colo: z.string().nullable().optional(),
            latency_ms: z.number().nullable().optional(),
            download_mbps: z.number().nullable().optional(),
            upload_mbps: z.number().nullable().optional()
        }).nullable().optional(),
        geekbench: z.object({
            single: z.number(),
            multi: z.number(),
            version: z.union([z.number(), z.string()]).nullable().optional(),
            url: z.string().nullable().optional()
        }).nullable().optional(),
        disk: z.array(z.object({
            bs: z.string(),
            speed_r: z.number().nullable().optional(),
            speed_w: z.number().nullable().optional(),
            speed_rw: z.number().nullable().optional()
        })).nullable().optional()
    })
})

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
                exclude: ['index.md', 'runs/**']
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
        }),
        // Community-submitted benchmark runs. Each file is one PR:
        //   { submission: <CI-stamped>, run: <trimmed app run document> }
        runs: defineCollection({
            type: 'data',
            source: 'runs/*.json',
            schema: z.object({
                submission: z.object({
                    // The authenticated issue author, recorded by the submission bot.
                    // Empty is fine — the run just renders without an avatar.
                    github: z.string().default(''),
                    submitted_at: z.string(),
                    // true only for maintainer-run reference anchors.
                    verified: z.boolean().default(false)
                }),
                run: runDocument
            })
        })
    }
})
