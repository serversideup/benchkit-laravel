<script setup lang="ts">
/**
 * What a run exercises, grouped by the tool that drives it. The paths are the
 * real ones from routes/bench.php.
 *
 * Each row says what the measurement is *for*, not what it does. "20 rows read"
 * is trivia; "what your database adds to a request" is the reason the route
 * exists and the thing somebody is trying to find out.
 */
const GROUPS = [
    {
        label: 'HTTP routes',
        tool: 'oha',
        rows: [
            { method: 'GET', name: '/bench/static', does: 'Your ceiling with no work to do' },
            { method: 'GET', name: '/bench/json', does: 'What a typical API response costs' },
            { method: 'GET', name: '/bench/db-read', does: 'What your database adds to a request' },
            { method: 'GET', name: '/bench/io', does: 'Whether your server handles waiting well' }
        ]
    },
    {
        label: 'PHP and database',
        tool: 'phpbench',
        rows: [
            { name: 'Eloquent', does: 'What the ORM costs on your hardware' },
            { name: 'PHP', does: 'Where your PHP build is fast and slow' }
        ]
    },
    {
        label: 'Yours to change',
        rows: [
            { name: 'Database', does: 'SQLite, MySQL, MariaDB, Postgres, SQL Server' },
            { name: 'Load', does: 'Duration, connections, simulated I/O' },
            { name: 'Stages', does: 'Run all four or just one' }
        ]
    }
]
</script>

<template>
    <section class="border-t border-white/[0.06]">
        <UContainer class="py-20 lg:py-28">
            <!-- Two columns from xl. The panel alone against the left edge left
                 half the viewport empty with nothing to balance it. -->
            <div class="grid gap-12 xl:grid-cols-[1fr_1.3fr] xl:items-center xl:gap-20">
                <HomeReveal>
                    <HomeSectionHeading eyebrow="The workload">
                        <template #title>
                            <span class="block">Four routes that look like</span>
                            <span class="block text-neutral-500">a real Laravel app.</span>
                        </template>
                        <template #description>
                            Each one isolates a different part of your stack, so a slow number
                            tells you where to look.
                        </template>
                    </HomeSectionHeading>

                    <p class="mt-8 max-w-md text-pretty text-sm leading-relaxed text-neutral-500">
                        The workload itself stays fixed, so your run and everyone else's measure
                        the same thing. Everything around it is yours.
                    </p>
                </HomeReveal>

                <HomeReveal :delay="120">
                    <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.02]">
                        <!-- Hairline along the top edge -->
                        <div
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-x-16 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                        />

                        <div
                            v-for="(group, groupIndex) in GROUPS"
                            :key="group.label"
                            :class="groupIndex ? 'border-t border-white/[0.08]' : ''"
                        >
                            <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] px-6 py-4">
                                <span class="text-xs text-neutral-500">
                                    {{ group.label }}
                                </span>
                                <span
                                    v-if="group.tool"
                                    class="text-xs text-neutral-600"
                                >
                                    via {{ group.tool }}
                                </span>
                            </div>

                            <ul>
                                <li
                                    v-for="(row, rowIndex) in group.rows"
                                    :key="row.name"
                                    class="grid gap-1 px-6 py-4 transition-colors duration-200 hover:bg-white/[0.02] sm:grid-cols-[14rem_minmax(0,1fr)] sm:items-baseline sm:gap-6"
                                    :class="rowIndex ? 'border-t border-white/[0.05]' : ''"
                                >
                                    <span class="font-mono text-sm text-white">
                                        <span
                                            v-if="row.method"
                                            class="mr-2 text-neutral-600"
                                        >{{ row.method }}</span>{{ row.name }}
                                    </span>
                                    <span class="text-sm text-neutral-400">{{ row.does }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </HomeReveal>
            </div>
        </UContainer>
    </section>
</template>
