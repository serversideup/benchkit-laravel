<template>
    <!-- Same visual language as the results card: accent bar, brand row,
         glow, sans labels / mono data, quiet footer. -->
    <TemplateFrame :host="sharedHost" :timestamp="display.timestamp"
        glow="radial-gradient(ellipse 760px 460px at 50% 30%, rgba(230, 46, 5, 0.12), transparent 62%)">
        <!-- Hero: A vs B with the headline delta between them -->
        <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 40px; min-height: 0;">
            <div style="display: flex; flex-direction: column; min-width: 0;">
                <span :style="`font-size: 24px; color: #94979C; font-family: ${MONO}; font-weight: 500;`">{{ sideLabels.a }}</span>
                <div v-if="headline" style="display: flex; align-items: baseline; margin-top: 4px;">
                    <span :style="`color: #94979C; font-size: 118px; font-family: ${MONO}; font-weight: 800; line-height: 1;`">{{ headline.formatA }}</span>
                    <span v-if="headline.unit" :style="`color: #61656C; font-size: 30px; font-family: ${MONO}; margin-left: 14px;`">{{ headline.unit }}</span>
                </div>
                <span v-if="sideDiffs.a" :style="`font-size: 19px; color: #94979C; font-family: ${SANS}; font-weight: 500; margin-top: 10px;`">{{ sideDiffs.a }}</span>
            </div>

            <div v-if="headline" style="display: flex; flex-direction: column; align-items: center; flex-shrink: 0;">
                <span v-if="headline.percentLabel" :style="`color: ${headline.improved ? '#47CD89' : '#F97066'}; font-size: 72px; font-family: ${MONO}; font-weight: 800; line-height: 1;`">{{ headline.percentLabel }}</span>
                <span v-else :style="`color: #94979C; font-size: 40px; font-family: ${MONO}; font-weight: 700;`">vs</span>
                <span :style="`font-size: 19px; color: #94979C; font-family: ${SANS}; font-weight: 500; margin-top: 12px;`">{{ headline.label }}<template v-if="headline.unit"> ({{ headline.unit }})</template></span>
            </div>

            <div style="display: flex; flex-direction: column; align-items: flex-end; text-align: right; min-width: 0;">
                <span :style="`font-size: 24px; color: #CECFD2; font-family: ${MONO}; font-weight: 500;`">{{ sideLabels.b }}</span>
                <div v-if="headline" style="display: flex; align-items: baseline; margin-top: 4px;">
                    <span :style="`color: #FFF; font-size: 118px; font-family: ${MONO}; font-weight: 800; line-height: 1;`">{{ headline.formatB }}</span>
                    <span v-if="headline.unit" :style="`color: #94979C; font-size: 30px; font-family: ${MONO}; margin-left: 14px;`">{{ headline.unit }}</span>
                </div>
                <span v-if="sideDiffs.b" :style="`font-size: 19px; color: #94979C; font-family: ${SANS}; font-weight: 500; margin-top: 10px;`">{{ sideDiffs.b }}</span>
            </div>
        </div>

        <!-- Shared context: facts true of BOTH runs live here exactly once -->
        <div v-if="sharedChips.length" style="display: flex; flex-wrap: wrap; gap: 12px; padding-bottom: 26px;">
            <span v-for="chip in sharedChips" :key="chip"
                :style="`display: inline-flex; align-items: center; padding: 8px 18px; border-radius: 9999px; font-size: 19px; font-weight: 500; font-family: ${MONO}; border: 1px solid #262B35; background-color: #12151B; color: #CECFD2;`">
                {{ chip }}
            </span>
        </div>

        <!-- Secondary deltas -->
        <div v-if="secondaryDeltas.length" style="border-top: 1px solid #1D222B; padding: 20px 0 24px; display: flex; flex-direction: column; gap: 12px;">
            <div v-for="delta in secondaryDeltas" :key="delta.path" style="display: flex; align-items: baseline; justify-content: space-between;">
                <span :style="`font-size: 20px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">{{ delta.label }} <span style="color: #61656C;" v-if="delta.unit">({{ delta.unit }})</span></span>
                <span :style="`font-size: 22px; font-family: ${MONO}; font-weight: 500;`">
                    <span style="color: #94979C;">{{ formatNumber(delta.a) }}</span>
                    <span style="color: #61656C;"> &rarr; </span>
                    <span style="color: #F7F7F7;">{{ formatNumber(delta.b) }}</span>
                    <span :style="`color: ${delta.improved === null ? '#61656C' : delta.improved ? '#47CD89' : '#F97066'}; margin-left: 18px;`">{{ percentLabel(delta) }}</span>
                </span>
            </div>
        </div>
    </TemplateFrame>
</template>

<script setup>
import { computed } from 'vue';
import TemplateFrame from '@/Components/Share/templates/TemplateFrame.vue';
import { runDisplay, serverLabelFor } from '@/Composables/useRunSummary';
import { compareRuns, headlineDelta } from '@/Composables/useRunComparison';
import { MONO, SANS } from '@/share/templateStyles';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
    runB: {
        type: Object,
        required: true,
    },
});

const display = computed(() => runDisplay(props.runB));
const comparison = computed(() => compareRuns(props.run, props.runB));

// Poster numbers: whole above 10, one decimal of precision below
const formatNumber = (value) => {
    if( Math.abs(value) >= 10 ) {
        return Math.round(value).toLocaleString();
    }

    return String(parseFloat(value.toFixed(2)));
};

const percentLabel = (delta) => {
    if( delta.improved === null || delta.percent == null ) {
        return '~even';
    }

    return `${delta.percent > 0 ? '+' : ''}${delta.percent.toFixed(0)}%`;
};

const headline = computed(() => {
    const delta = headlineDelta(comparison.value.metricDeltas);

    if( !delta ) {
        return null;
    }

    return {
        ...delta,
        formatA: formatNumber(delta.a),
        formatB: formatNumber(delta.b),
        percentLabel: delta.improved === null ? null : percentLabel(delta),
    };
});

const secondaryDeltas = computed(() => Object.values(comparison.value.metricDeltas).flat()
    .filter((delta) => delta.path !== headline.value?.path)
    .sort((a, b) => Math.abs(b.percent ?? 0) - Math.abs(a.percent ?? 0))
    .slice(0, 4));

// Prefer the server variation, but when both sides read the same, fall
// through to the run labels, then timestamps, so A and B always differ
const sideLabels = computed(() => {
    const clip = (value) => (value || '').slice(0, 28);
    const pairs = [
        [serverLabelFor(props.run), serverLabelFor(props.runB)],
        [props.run.meta.label, props.runB.meta.label],
        [runDisplay(props.run).timestamp, runDisplay(props.runB).timestamp],
        ['Run A', 'Run B'],
    ];

    for (const [a, b] of pairs) {
        if( a && b && a !== b ) {
            return { a: clip(a), b: clip(b) };
        }
    }

    return { a: 'Run A', b: 'Run B' };
});

// A comparison should only attach facts to a side when they DIFFER between
// sides — anything shared is stated once: hosting in the corner block,
// stack versions in the chips row
const contextParts = (run) => ({
    php: run.environment?.php?.php_version ? `PHP ${run.environment.php.php_version}` : null,
    laravel: run.environment?.laravel?.environment?.laravel_version ? `Laravel ${run.environment.laravel.environment.laravel_version}` : null,
    provider: run.meta.provider ?? null,
    plan: run.meta.plan ?? run.meta.plan_notes ?? null,
    datacenter: run.meta.datacenter ?? null,
    cost: run.meta.cost ?? null,
});

const sharedKeys = computed(() => {
    const a = contextParts(props.run);
    const b = contextParts(props.runB);

    return Object.keys(a).filter((key) => a[key] && a[key] === b[key]);
});

const sharedHost = computed(() => {
    const parts = contextParts(props.run);
    const provider = sharedKeys.value.includes('provider') ? parts.provider : null;
    const details = ['plan', 'datacenter', 'cost']
        .filter((key) => sharedKeys.value.includes(key))
        .map((key) => parts[key])
        .join(' · ');

    if( !provider && !details ) {
        return null;
    }

    return { provider, details: details || null };
});

const sharedChips = computed(() => {
    const parts = contextParts(props.run);

    return ['php', 'laravel']
        .filter((key) => sharedKeys.value.includes(key))
        .map((key) => parts[key]);
});

const sideDiffs = computed(() => {
    const a = contextParts(props.run);
    const b = contextParts(props.runB);
    const differing = Object.keys(a).filter((key) => (a[key] || b[key]) && a[key] !== b[key]);

    return {
        a: differing.map((key) => a[key]).filter(Boolean).join(' · ') || null,
        b: differing.map((key) => b[key]).filter(Boolean).join(' · ') || null,
    };
});
</script>
