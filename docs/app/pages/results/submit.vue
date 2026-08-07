<template>
    <UContainer class="mx-auto max-w-[960px] py-10">
        <UButton
            to="/results"
            variant="ghost"
            color="neutral"
            size="sm"
            icon="i-lucide-arrow-left"
            class="mb-6"
        >
            All results
        </UButton>

        <h1 class="text-2xl sm:text-3xl font-bold text-[#F7F7F7]">
            Check a submission
        </h1>
        <p class="mt-2 text-sm text-[#94979C] max-w-2xl">
            Paste the token BenchKit gave you to see exactly what would be published, and to catch anything
            that would fail review before you open an issue. Nothing is uploaded — the token is unpacked
            here in your browser.
        </p>

        <!-- Input -->
        <div
            class="mt-6 rounded-2xl border border-[#22262F] bg-[#0C0E12] p-5 transition-colors"
            :class="dragging ? 'border-[#E62E05]' : ''"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <label
                for="token"
                class="text-xs uppercase tracking-wide text-[#61656C]"
            >Submission token</label>
            <textarea
                id="token"
                v-model="input"
                rows="4"
                spellcheck="false"
                placeholder="bk1.…"
                class="mt-2 w-full resize-y rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 font-mono text-xs text-[#F7F7F7] break-all placeholder:text-[#61656C] focus:border-[#61656C] focus:outline-none"
            />
            <p class="mt-2 text-xs text-[#61656C]">
                You can also drop the file you saved it in.
            </p>
        </div>

        <!-- Decode / validation failure -->
        <div
            v-if="problem"
            class="mt-6 rounded-2xl border border-[#373A41] bg-[#0C0E12] p-5"
        >
            <p class="flex items-center gap-2 text-sm font-medium text-[#F7F7F7]">
                <UIcon
                    name="i-lucide-triangle-alert"
                    class="size-4 text-[#F79009]"
                />
                {{ problem }}
            </p>
        </div>

        <template v-if="preview">
            <!-- Would this be accepted? The same checks the pull-request
                 validator runs, so the answer here is the answer there. -->
            <div
                class="mt-6 rounded-2xl border p-5"
                :class="errors.length ? 'border-[#F97066]/40 bg-[#F97066]/5' : 'border-[#47CD89]/30 bg-[#47CD89]/5'"
            >
                <p class="flex items-center gap-2 text-sm font-medium text-[#F7F7F7]">
                    <UIcon
                        :name="errors.length ? 'i-lucide-circle-x' : 'i-lucide-circle-check'"
                        class="size-4"
                        :class="errors.length ? 'text-[#F97066]' : 'text-[#47CD89]'"
                    />
                    {{ errors.length ? 'This would be rejected' : 'Ready to submit' }}
                </p>
                <ul
                    v-if="errors.length"
                    class="mt-3 flex flex-col gap-1.5 text-sm text-[#CECFD2]"
                >
                    <li
                        v-for="error in errors"
                        :key="error"
                        class="flex gap-2"
                    >
                        <span class="text-[#F97066]">•</span>{{ error }}
                    </li>
                </ul>
                <ul
                    v-if="warnings.length"
                    class="mt-3 flex flex-col gap-1.5 text-sm text-[#94979C]"
                >
                    <li
                        v-for="warning in warnings"
                        :key="warning"
                        class="flex gap-2"
                    >
                        <UIcon
                            name="i-lucide-info"
                            class="size-4 shrink-0 mt-0.5 text-[#F79009]"
                        />{{ warning }}
                    </li>
                </ul>
            </div>

            <!-- At-a-glance spec strip, same shape the result page uses -->
            <h2 class="mt-8 text-lg font-semibold text-[#F7F7F7]">
                {{ preview.meta.label }}
            </h2>
            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div
                    v-for="spec in specs"
                    :key="spec.label"
                    class="rounded-xl border border-[#22262F] bg-[#0C0E12] p-4"
                >
                    <div class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-[#61656C]">
                        <UIcon
                            :name="spec.icon"
                            class="size-3.5"
                        /> {{ spec.label }}
                    </div>
                    <div class="mt-2 text-lg font-semibold text-[#F7F7F7] leading-tight break-words">
                        {{ spec.value }}
                    </div>
                    <div
                        v-if="spec.sub"
                        class="text-xs text-[#94979C] mt-0.5 break-words"
                    >
                        {{ spec.sub }}
                    </div>
                </div>
            </div>

            <div
                v-if="metrics.length"
                class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-3"
            >
                <div
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="rounded-xl border border-[#22262F] bg-[#0C0E12] p-4"
                >
                    <div class="font-mono text-2xl text-[#F7F7F7] tabular-nums">
                        {{ metric.value }}
                    </div>
                    <div class="mt-1 text-xs text-[#61656C]">
                        {{ metric.label }}
                    </div>
                </div>
            </div>

            <!-- The exhaustive answer to "what am I publishing?". The summary
                 above is a reading of this; this is the thing itself. -->
            <details class="mt-6 rounded-2xl border border-[#22262F] bg-[#0C0E12] overflow-hidden">
                <summary class="cursor-pointer px-5 py-4 text-sm text-[#CECFD2] hover:text-[#F7F7F7]">
                    Everything in this submission, in full
                </summary>
                <pre class="max-h-[28rem] overflow-auto border-t border-[#22262F] px-5 py-4 text-xs font-mono text-[#94979C]">{{ json }}</pre>
            </details>

            <div class="mt-4 rounded-2xl border border-[#22262F] bg-[#0C0E12] p-5">
                <p class="text-sm font-medium text-[#F7F7F7]">
                    Not in it
                </p>
                <p class="mt-2 text-sm text-[#94979C]">
                    Console logs, your <code class="font-mono text-[#CECFD2]">APP_URL</code> and internal hostnames,
                    the raw YABS output with its IP/ISP/city block, your network ASN and Cloudflare colo, and your
                    <code class="font-mono text-[#CECFD2]">opcache.preload</code> path. The app never puts them in a
                    token, and a second check on the pull request scans every value again before anything merges.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <UButton
                    :to="issueUrl"
                    target="_blank"
                    color="primary"
                    size="lg"
                    trailing-icon="i-lucide-arrow-up-right"
                    :disabled="errors.length > 0"
                >
                    Open a submission issue
                </UButton>
                <UButton
                    color="neutral"
                    variant="outline"
                    size="lg"
                    :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                    @click="copy"
                >
                    {{ copied ? 'Copied' : 'Copy token' }}
                </UButton>
            </div>
            <p class="mt-3 text-xs text-[#61656C]">
                Paste the token into the code block on the issue if it isn't already there. A bot validates it,
                opens a pull request, and credits the run to your GitHub account.
            </p>
        </template>
    </UContainer>
</template>

<script setup lang="ts">
import { decodeToken, findToken } from '~~/shared/submission/token.mjs'
import { buildDocument } from '~~/shared/submission/run-document.mjs'
import { validateSubmission } from '~~/shared/submission/validate.mjs'
import type { HttpRoute, RunEntry } from '~/types/run'
import { coresLabel, costLabel } from '~/types/run'

/** What a token carries: the run, before the bot wraps it in index fields. */
type SubmittedRun = RunEntry['run']

const REPO = 'serversideup/benchkit-laravel'
const MARKER = '<!-- benchkit-result-submission -->'

const input = ref('')
const dragging = ref(false)
const copied = ref(false)

const preview = ref<SubmittedRun | null>(null)
const problem = ref<string | null>(null)
const errors = ref<string[]>([])
const warnings = ref<string[]>([])

const token = computed(() => findToken(input.value))

// Decoding is async (DecompressionStream, crypto.subtle), so it can't be a
// computed. A sequence number keeps a slow decode from overwriting a newer
// paste's result.
let generation = 0

watch(input, async () => {
    const current = ++generation

    preview.value = null
    problem.value = null
    errors.value = []
    warnings.value = []
    copied.value = false

    if (!input.value.trim()) return

    if (!token.value) {
        problem.value = input.value.includes('bk1.')
            ? 'That token looks cut short — copy the whole thing, including the checksum after the final dot.'
            : 'That doesn\'t look like a submission token. In BenchKit, open your run and click Submit result.'
        return
    }

    if (typeof DecompressionStream === 'undefined') {
        problem.value = 'This browser can\'t unpack submission tokens. Try a current version of Chrome, Firefox, Edge, or Safari.'
        return
    }

    let run: SubmittedRun

    try {
        run = await decodeToken(token.value) as SubmittedRun
    } catch (error) {
        if (current === generation) problem.value = (error as Error).message
        return
    }

    // Exactly what the bot does with an accepted token, so a clean result here
    // means a clean result there.
    const document = await buildDocument(run, {
        github: '',
        submittedAt: String(run.created_at ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
        verified: false
    })

    const result = await validateSubmission(document, null)

    if (current !== generation) return

    preview.value = document.run
    errors.value = result.errors
    warnings.value = result.warnings
})

const onDrop = async (event: DragEvent) => {
    dragging.value = false

    const file = event.dataTransfer?.files?.[0]

    if (file) input.value = await file.text()
}

const copy = async () => {
    if (!token.value) return

    try {
        await navigator.clipboard.writeText(token.value)
        copied.value = true
        setTimeout(() => copied.value = false, 2000)
    } catch {
        // Clipboard denied — the textarea above is still selectable.
    }
}

const json = computed(() => preview.value ? JSON.stringify(preview.value, null, 2) : '')

const issueUrl = computed(() => {
    if (!preview.value || !token.value) return '#'

    const body = [
        MARKER,
        '',
        `Submitting **${preview.value.meta?.label ?? 'a BenchKit run'}** to the community gallery.`,
        '',
        '```',
        token.value,
        '```'
    ].join('\n')

    const params = new URLSearchParams({
        title: `Result: ${preview.value.meta?.label ?? 'BenchKit run'}`,
        labels: 'result-submission',
        body
    })

    return `https://github.com/${REPO}/issues/new?${params.toString()}`
})

const VARIATION_LABELS: Record<string, string> = {
    'frankenphp': 'FrankenPHP',
    'fpm-nginx': 'fpm-nginx',
    'fpm-apache': 'fpm-Apache'
}

const formatRam = (ram?: string) => {
    const mb = Number.parseFloat(String(ram))

    if (Number.isNaN(mb)) return ram ?? '—'

    return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${Math.round(mb)} MB`
}

const specs = computed(() => {
    const run = preview.value!
    const server = run.environment?.server ?? {}
    const php = run.environment?.php ?? {}
    const meta = run.meta ?? {}

    return [
        {
            label: 'Host',
            icon: 'i-lucide-server',
            value: meta.provider || 'Self-hosted',
            sub: [meta.plan, meta.datacenter, costLabel(meta.cost)].filter(Boolean).join(' · ') || null
        },
        {
            label: 'Hardware',
            icon: 'i-lucide-cpu',
            value: `${coresLabel(server.cpu_cores)} · ${formatRam(server.ram)}`,
            sub: server.cpu_model
        },
        {
            label: 'Server',
            icon: 'i-lucide-boxes',
            value: VARIATION_LABELS[php.php_variation] ?? php.php_variation ?? '—',
            sub: `PHP ${php.php_version}${php.octane ? ' · Octane' : ''}`
        },
        {
            label: 'Stages',
            icon: 'i-lucide-list-checks',
            value: `${run.stages_completed?.length ?? 0} of 4`,
            sub: run.stages_completed?.join(', ') || null
        }
    ]
})

const metrics = computed(() => {
    const benchmarks = preview.value!.benchmarks ?? {}
    // Keyed lookup over a shape declared key-by-key, same as the result page.
    const routes = (benchmarks.http?.routes ?? {}) as Record<string, HttpRoute | undefined>
    const hero = ['db_read', 'json', 'static'].map(key => routes[key]).find(row => row?.requests_per_second != null)
    const ms = (value: number) => value >= 1 ? `${value.toFixed(1)}ms` : `${Math.round(value * 1000)}µs`

    return [
        hero && { label: 'requests/sec', value: Math.round(hero.requests_per_second).toLocaleString('en-US') },
        hero?.p95_ms != null && { label: 'p95 latency', value: ms(hero.p95_ms) },
        benchmarks.php?.headline?.read?.milliseconds != null && { label: 'PHP read', value: ms(benchmarks.php.headline.read.milliseconds) },
        benchmarks.geekbench && { label: 'Geekbench multi', value: benchmarks.geekbench.multi?.toLocaleString('en-US') },
        benchmarks.cfspeedtest?.download_mbps != null && { label: 'download', value: `${Math.round(benchmarks.cfspeedtest.download_mbps)} Mbps` }
    ].filter(Boolean).slice(0, 4) as { label: string, value: string }[]
})

useSeoMeta({
    title: 'Check a submission — BenchKit',
    description: 'Unpack a BenchKit submission token in your browser to see exactly what would be published to the community results gallery.'
})
</script>
