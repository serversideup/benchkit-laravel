<template>
    <figure v-if="points.length" class="m-0">
        <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" class="w-full h-auto overflow-visible" role="img"
            :aria-label="summary">
            <!-- The predicted ceiling, drawn from arithmetic rather than from
                 the data: a request on the I/O route holds a worker for its
                 whole simulated wait, so the pool can serve at most
                 workers x 1000/delay however fast the machine is. Having the
                 measurement land on a line that was drawn before it is the
                 clearest thing on this page. Solid and recessive — a dashed
                 rule reads as chrome, and this is an annotation. -->
            <g v-if="ceilingY !== null">
                <line :x1="0" :x2="WIDTH" :y1="ceilingY" :y2="ceilingY" stroke="#F79009" stroke-width="1"
                    stroke-opacity="0.45" />
                <text :x="WIDTH" :y="ceilingY - 5" text-anchor="end" class="fill-[#F79009] text-[9px] font-mono"
                    opacity="0.8">{{ ceilingLabel }}</text>
            </g>

            <path v-if="areaPath" :d="areaPath" fill="#E62E05" fill-opacity="0.10" />

            <!-- Split at the knee. Everything to the right buys queue rather
                 than throughput, and saying so with weight costs no words. -->
            <polyline v-if="risingPath" :points="risingPath" fill="none" stroke="#E62E05" stroke-width="2"
                stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            <polyline v-if="flatPath" :points="flatPath" fill="none" stroke="#61656C" stroke-width="2"
                stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

            <!-- Hover without a line of JavaScript, and it survives being
                 rasterised onto a share card. -->
            <g v-for="point in plotted" :key="point.concurrency">
                <circle :cx="point.x" :cy="point.y" r="9" fill="transparent" />
                <circle :cx="point.x" :cy="point.y" :r="point.isKnee ? 4 : 2.5"
                    :fill="point.isKnee ? '#F7F7F7' : '#E62E05'"
                    :stroke="point.isKnee ? '#E62E05' : 'none'" :stroke-width="point.isKnee ? 2 : 0" />
                <title>{{ point.tooltip }}</title>
            </g>

            <line :x1="0" :x2="WIDTH" :y1="HEIGHT" :y2="HEIGHT" stroke="#22262F" stroke-width="1" />

            <text v-for="tick in ticks" :key="tick.concurrency" :x="tick.x" :y="HEIGHT + 12"
                :text-anchor="tick.anchor" class="fill-[#61656C] text-[9px] font-mono">{{ tick.concurrency }}</text>
        </svg>
        <figcaption class="mt-3.5 text-[10px] text-[#61656C] font-mono">concurrent requests</figcaption>
    </figure>
</template>

<script setup>
import { computed } from 'vue';

/**
 * Throughput against concurrency for one route.
 *
 * Four of these stack rather than becoming one chart with four series. A
 * shared linear y-axis would pin the I/O route flat against the axis while the
 * framework routes use the whole height — destroying the one moment worth
 * showing — and a log axis fixes the range at the cost of the audience, who
 * has not read a doc about log scales. Independent y-axes with the x-axes
 * aligned keep what actually matters across routes: *where* each one bends.
 *
 * One series per chart also means no legend and no categorical palette: the
 * red/grey split is emphasis (rising against past-the-knee), not identity.
 */
const props = defineProps({
    points: {
        type: Array,
        default: () => [],
    },
    knee: {
        type: Number,
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    /** Throughput arithmetic predicts, when the route has one. */
    predictedRps: {
        type: Number,
        default: null,
    },
    predictedLabel: {
        type: String,
        default: '',
    },
});

const WIDTH = 320;
const HEIGHT = 88;

const peak = computed(() => Math.max(
    1,
    ...props.points.map((point) => point.requests_per_second ?? 0),
    props.predictedRps ?? 0,
));

/**
 * x is spaced by index, not by value.
 *
 * The levels roughly double, so a linear x-axis piles the first four into the
 * left eighth of the plot and the bend — the whole point — lands somewhere
 * unreadable. Even spacing is also what a reader intuits from a doubling
 * ladder. The ticks carry the real numbers.
 */
const plotted = computed(() => {
    const gap = props.points.length > 1 ? WIDTH / (props.points.length - 1) : 0;

    return props.points.map((point, index) => {
        const rps = point.requests_per_second ?? 0;

        return {
            ...point,
            x: props.points.length > 1 ? index * gap : WIDTH / 2,
            y: HEIGHT - (rps / peak.value) * HEIGHT,
            isKnee: point.concurrency === props.knee,
            tooltip: [
                `${point.concurrency} concurrent`,
                `${Math.round(rps).toLocaleString()} req/s`,
                point.p50_ms != null ? `p50 ${point.p50_ms}ms` : null,
            ].filter(Boolean).join(' · '),
        };
    });
});

const kneeIndex = computed(() => {
    const index = plotted.value.findIndex((point) => point.isKnee);

    return index === -1 ? plotted.value.length - 1 : index;
});

const asPoints = (list) => list.map((point) => `${point.x},${point.y}`).join(' ');

const risingPath = computed(() => {
    const segment = plotted.value.slice(0, kneeIndex.value + 1);

    return segment.length > 1 ? asPoints(segment) : null;
});

const flatPath = computed(() => {
    const segment = plotted.value.slice(kneeIndex.value);

    return segment.length > 1 ? asPoints(segment) : null;
});

const areaPath = computed(() => {
    if (plotted.value.length < 2) {
        return null;
    }

    const first = plotted.value[0];
    const last = plotted.value[plotted.value.length - 1];

    return `M ${first.x},${HEIGHT} L ${asPoints(plotted.value).replaceAll(' ', ' L ')} L ${last.x},${HEIGHT} Z`;
});

const ceilingY = computed(() => props.predictedRps
    ? HEIGHT - (props.predictedRps / peak.value) * HEIGHT
    : null);

const ceilingLabel = computed(() => props.predictedLabel);

/**
 * Three ticks rather than one per level. Labelling every point crowds a plot
 * this size into illegibility, and the ones worth naming are the ends and the
 * bend — which is also the number a reader is meant to recognise as their own.
 */
const ticks = computed(() => {
    const wanted = new Set([0, kneeIndex.value, plotted.value.length - 1]);

    return plotted.value
        .map((point, index) => ({ ...point, index }))
        .filter((point) => wanted.has(point.index))
        .map((point) => ({
            concurrency: point.concurrency,
            x: point.x,
            anchor: point.index === 0 ? 'start' : (point.index === plotted.value.length - 1 ? 'end' : 'middle'),
        }));
});

const summary = computed(() => {
    const kneePoint = plotted.value[kneeIndex.value];

    return `${props.label}: throughput against concurrency, peaking near ${kneePoint?.concurrency ?? '?'} concurrent requests.`;
});
</script>
