<template>
    <div class="w-full">
        <div class="w-full max-w-4xl mx-auto flex flex-col py-12 px-4">
            <Link href="/runs" class="text-sm text-[#94979C] hover:text-[#CECFD2]">&larr; Run history</Link>

            <div class="mt-6 flex items-center justify-between gap-5">
                <h1 class="text-2xl sm:text-3xl font-semibold text-[#F7F7F7]">Compare runs</h1>
                <button @click="shareOpen = true" type="button" class="px-4 py-2.5 rounded-lg flex items-center text-sm font-medium shadow-sm text-white bg-[#E62E05] border border-[#E62E05] hover:bg-[#F13D12] hover:border-[#F13D12] transition-colors duration-200 cursor-pointer shrink-0">
                    Share on
                    <IconXLogo class="ml-2" />
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="side in ['a', 'b']" :key="side" class="flex flex-col gap-1.5">
                    <label :for="`run-${side}`" class="text-sm text-[#94979C]">Run {{ side.toUpperCase() }}</label>
                    <select :id="`run-${side}`" :value="side === 'a' ? runA.id : runB.id" @change="swapRun(side, $event.target.value)"
                        class="rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2.5 text-sm text-[#F7F7F7] font-mono focus:outline-none focus:border-[#61656C] cursor-pointer">
                        <option v-for="run in runs" :key="run.id" :value="run.id">
                            {{ run.meta.label }} — {{ formatUTCTimestamp(run.created_at) }}
                        </option>
                    </select>
                    <p class="text-xs text-[#94979C] font-mono">
                        {{ formatUTCTimestamp(side === 'a' ? runA.created_at : runB.created_at) }}
                        <template v-if="hostLine(side === 'a' ? runA : runB)"> &middot; {{ hostLine(side === 'a' ? runA : runB) }}</template>
                    </p>
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-[#22262F] bg-[#0C0E12] px-6 sm:px-8 divide-y divide-[#22262F]">
                <section v-if="headline" class="py-10">
                    <!-- Three identical column anatomies (caption, then value)
                         so every baseline aligns across the hero -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-end gap-8">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#94979C]">Run A</p>
                            <p class="mt-2 flex items-baseline gap-2.5">
                                <span class="text-6xl font-mono font-medium text-[#94979C] leading-none">{{ heroNumber(headline.a) }}</span>
                                <span v-if="headline.unit" class="text-lg font-mono text-[#61656C]">{{ headline.unit }}</span>
                            </p>
                        </div>
                        <div class="text-center min-w-0">
                            <p class="text-sm font-medium text-[#94979C]">{{ headline.label }}<template v-if="headline.unit"> ({{ headline.unit }})</template></p>
                            <p class="mt-2">
                                <span class="text-6xl font-mono font-medium leading-none" :class="headline.improved === null ? 'text-[#61656C]' : headline.improved ? 'text-[#47CD89]' : 'text-[#F97066]'">{{ heroPercent }}</span>
                            </p>
                        </div>
                        <div class="min-w-0 text-right">
                            <p class="text-sm font-medium text-[#94979C]">Run B</p>
                            <p class="mt-2 flex items-baseline justify-end gap-2.5">
                                <span class="text-6xl font-mono font-medium text-[#F7F7F7] leading-none">{{ heroNumber(headline.b) }}</span>
                                <span v-if="headline.unit" class="text-lg font-mono text-[#94979C]">{{ headline.unit }}</span>
                            </p>
                        </div>
                    </div>

                    <p v-if="httpLoadMismatch && headline.path.startsWith('routes.')" class="mt-7 text-sm text-center text-[#F79009]">
                        These runs used different load settings &mdash; Run A: <span class="font-mono">{{ httpLoadMismatch.a }}</span> &middot; Run B: <span class="font-mono">{{ httpLoadMismatch.b }}</span> &mdash; so throughput isn't directly comparable.
                    </p>
                </section>

                <section v-for="stage in Object.keys(comparison.metricDeltas)" :key="stage" class="py-9">
                    <h2 class="text-base font-semibold text-[#F7F7F7]">{{ STAGE_HEADINGS[stage] }}</h2>
                    <p v-if="stage === 'http' && httpLoadMismatch" class="mt-1.5 text-sm text-[#F79009]">
                        Different load settings &mdash; Run A: <span class="font-mono">{{ httpLoadMismatch.a }}</span> &middot; Run B: <span class="font-mono">{{ httpLoadMismatch.b }}</span>.
                    </p>

                    <div class="mt-5 rounded-lg border border-[#22262F] overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-[#22262F] text-[#94979C] text-sm">
                                    <th class="px-4 py-2.5 font-normal">Metric</th>
                                    <th class="px-4 py-2.5 font-normal text-right">Run A</th>
                                    <th class="px-4 py-2.5 font-normal text-right">Run B</th>
                                    <th class="px-4 py-2.5 font-normal text-right">Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                <DeltaRow v-for="delta in comparison.metricDeltas[stage]" :key="delta.path" :delta="delta" />
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="!Object.keys(comparison.metricDeltas).length" class="py-7">
                    <p class="text-sm text-[#94979C]">
                        These runs have no completed stages in common, so there are no metrics to compare.
                    </p>
                </section>

                <section class="py-9">
                    <h2 class="text-base font-semibold text-[#F7F7F7]">What changed</h2>
                    <p class="mt-1.5 text-xs text-[#94979C]">Configuration differences between the two runs &mdash; the cause behind the deltas above.</p>

                    <DiffList v-if="changes.length" class="mt-6" :diffs="changes" />
                    <p v-else class="mt-5 text-sm text-[#94979C]">
                        Identical settings and environment &mdash; any deltas above are run-to-run variance.
                    </p>
                </section>
            </div>
        </div>

        <ShareModal :open="shareOpen" :run="runA" :run-b="runB" @close="shareOpen = false" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/App.vue';
import IconXLogo from '@/Components/Icons/IconXLogo.vue';
import DeltaRow from '@/Components/Runs/DeltaRow.vue';
import DiffList from '@/Components/Runs/DiffList.vue';
import ShareModal from '@/Components/Share/ShareModal.vue';
import { compareRuns, headlineDelta } from '@/Composables/useRunComparison';
import { STAGE_HEADINGS } from '@/stages';
import { formatUTCTimestamp, hostDetailsLine } from '@/Composables/useRunSummary';
import { useDocumentTitle } from '@/Composables/useDocumentTitle';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    runA: {
        type: Object,
        required: true,
    },
    runB: {
        type: Object,
        required: true,
    },
    runs: {
        type: Array,
        required: true,
    },
});

useDocumentTitle();

const comparison = computed(() => compareRuns(props.runA, props.runB));
const hostLine = (run) => hostDetailsLine(run.meta, ['provider', 'plan']);

const headline = computed(() => headlineDelta(comparison.value.metricDeltas));

// One list, environment facts first — the labels self-explain, so the old
// Environment/Settings sub-groups were structure without information
const changes = computed(() => [...comparison.value.environmentDiff, ...comparison.value.settingsDiff]);

// Reads the loadgen settings each run actually used from its stored http
// meta (not run.settings, which older snapshots don't carry) — differing
// loads make throughput numbers apples-to-oranges, so say so
const httpLoadMismatch = computed(() => {
    const a = props.runA.benchmarks?.http;
    const b = props.runB.benchmarks?.http;

    if( !a || !b ) {
        return null;
    }

    if( a.connections === b.connections && a.duration_seconds === b.duration_seconds ) {
        return null;
    }

    const label = (http) => `${http.connections ?? '?'} connections × ${http.duration_seconds ?? '?'}s`;

    return { a: label(a), b: label(b) };
});
const shareOpen = ref(false);

// Integers normally — but when rounding would make two genuinely different
// values read as equal (31.8 and 32.4 both showing "32" beside "+1.9%"),
// both sides gain one decimal so the numbers agree with the percentage
const heroNumber = (value) => {
    if( Math.abs(value) < 10 ) {
        return String(parseFloat(value.toFixed(2)));
    }

    const hidesDifference = headline.value
        && headline.value.a !== headline.value.b
        && Math.round(headline.value.a) === Math.round(headline.value.b);

    return hidesDifference
        ? value.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })
        : Math.round(value).toLocaleString();
};

// Always a number — insignificant changes show their honest tiny percentage
// in whisper gray rather than a giant "~even"
const heroPercent = computed(() => {
    const percent = headline.value?.percent;

    if( percent == null ) {
        return '—';
    }

    const decimals = Math.abs(percent) < 10 ? 1 : 0;

    return `${percent > 0 ? '+' : ''}${percent.toFixed(decimals)}%`;
});

const swapRun = (side, id) => {
    const a = side === 'a' ? id : props.runA.id;
    const b = side === 'b' ? id : props.runB.id;

    router.visit(`/compare/${a}/${b}`);
};
</script>
