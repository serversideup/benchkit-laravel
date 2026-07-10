<template>
    <!-- Inline styles only: this node is rasterized by html-to-image.
         Sized for feed consumption (~500px wide): minimum text 17px. -->
    <div :style="`display: flex; flex-direction: column; width: 1200px; height: 675px; background-color: #0C0E12; background-image: radial-gradient(ellipse 820px 500px at 18% 22%, rgba(230, 46, 5, 0.16), transparent 62%);`">
        <div style="height: 5px; flex-shrink: 0; background: linear-gradient(90deg, #E62E05, #F79009);"></div>

        <div style="flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 36px 64px 0;">
            <!-- Brand row: hosting details earn the corner; otherwise the timestamp keeps it -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                <img src="/images/results/title.png" style="height: 36px; display: block;"/>
                <div v-if="host" style="display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
                    <span v-if="host.provider" :style="`font-size: 22px; color: #F7F7F7; font-family: ${MONO}; font-weight: 600; line-height: 1.2;`">{{ host.provider }}</span>
                    <span v-if="host.details" :style="`font-size: 17px; color: #94979C; font-family: ${MONO}; margin-top: 2px;`">{{ host.details }}</span>
                </div>
                <span v-else :style="`font-size: 18px; color: #61656C; font-family: ${MONO};`">{{ display.timestamp }}</span>
            </div>

            <!-- Hero -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 48px; min-height: 0;">
                <div v-if="heroRoute" style="display: flex; flex-direction: column;">
                    <label :style="`font-size: 26px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">Web server throughput <span style="color: #61656C; font-weight: 400;">&middot; {{ heroRoute.label }}</span></label>
                    <div style="display: flex; align-items: baseline; margin-top: 2px;">
                        <span :style="`color: #FFF; font-size: ${heroFontSize}px; font-family: ${MONO}; font-weight: 800; line-height: 1;`">{{ heroNumber }}</span>
                        <span :style="`color: #94979C; font-size: 40px; font-family: ${MONO}; font-weight: 500; margin-left: 16px;`">req/s</span>
                    </div>
                    <span :style="`font-size: 22px; color: #94979C; font-family: ${MONO}; margin-top: 12px;`">{{ heroContext }}</span>
                </div>

                <div v-else-if="display.php" style="display: flex; flex-direction: column;">
                    <label :style="`font-size: 26px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">Laravel database performance <span style="color: #61656C; font-weight: 400;">&middot; {{ display.php.create.records ?? 100 }} records per operation</span></label>
                    <div style="display: flex; align-items: flex-end; gap: 52px; margin-top: 24px;">
                        <div v-for="operation in operations" :key="operation.key" style="display: flex; flex-direction: column;">
                            <span :style="`color: #FFF; font-size: 80px; font-family: ${MONO}; font-weight: 700; line-height: 1.05;`">{{ operation.parts.value }}<span :style="`font-size: 32px; color: #94979C; font-weight: 500; margin-left: 7px;`">{{ operation.parts.unit }}</span></span>
                            <span style="display: flex; align-items: center; margin-top: 8px;">
                                <img :src="`/images/results/${operation.key}.png`" style="width: 21px; margin-right: 7px;"/>
                                <span :style="`font-size: 20px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">{{ operation.label }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; text-align: right;">
                    <span v-if="display.environment.octane" :style="`background-color: #E62E05; color: #FFF; font-size: 17px; font-family: ${SANS}; font-weight: 700; padding: 6px 18px; border-radius: 9999px; letter-spacing: 2px; margin-bottom: 14px;`">OCTANE</span>
                    <span :style="`color: #FFF; font-size: 52px; font-family: ${MONO}; font-weight: 700; line-height: 1.1;`">{{ (run.environment?.php?.php_variation || run.environment?.php?.php_server_api || '').toUpperCase() }}</span>

                    <label v-if="showGeekbench" :style="`font-size: 19px; color: #CECFD2; font-family: ${SANS}; font-weight: 600; margin-top: 36px;`">Geekbench<span v-if="geekbenchVersion" style="color: #61656C; font-weight: 400;"> &middot; v{{ geekbenchVersion }}</span></label>
                    <div v-if="showGeekbench" style="display: flex; align-items: flex-end; gap: 36px; margin-top: 14px;">
                        <div style="display: flex; flex-direction: column; align-items: flex-end;">
                            <span :style="`color: #F7F7F7; font-size: 40px; font-family: ${MONO}; font-weight: 600; line-height: 1.05;`">{{ display.geekbench.single }}</span>
                            <span style="display: flex; align-items: center; margin-top: 6px;">
                                <img src="/images/results/single-core.png" style="width: 18px; margin-right: 6px;"/>
                                <span :style="`font-size: 17px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">single-core</span>
                            </span>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end;">
                            <span :style="`color: #F7F7F7; font-size: 40px; font-family: ${MONO}; font-weight: 600; line-height: 1.05;`">{{ display.geekbench.multi }}</span>
                            <span style="display: flex; align-items: center; margin-top: 6px;">
                                <img src="/images/results/multi-core.png" style="width: 18px; margin-right: 6px;"/>
                                <span :style="`font-size: 17px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">multi-core</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Environment chips: the top margin separates them from the
                 hero's percentile line, which sits only 12px under the big
                 number — the chips must read as their own group -->
            <div v-if="chips.length" style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; padding-bottom: 28px;">
                <span v-for="chip in chips" :key="chip.text"
                    :style="`display: inline-flex; align-items: center; padding: 8px 18px; border-radius: 9999px; font-size: 19px; font-weight: 500; font-family: ${MONO}; border: 1px solid ${chip.accent ? 'rgba(230, 46, 5, 0.55)' : '#262B35'}; background-color: ${chip.accent ? 'rgba(230, 46, 5, 0.10)' : '#12151B'}; color: ${chip.accent ? '#F7F7F7' : '#CECFD2'};`">
                    {{ chip.text }}
                </span>
            </div>

            <!-- Stats row -->
            <div v-if="statGroups.length" style="display: flex; align-items: flex-start; gap: 72px; border-top: 1px solid #1D222B; padding: 22px 0 26px;">
                <div v-for="group in statGroups" :key="group.label" style="display: flex; flex-direction: column;">
                    <label :style="`font-size: 19px; color: #CECFD2; font-family: ${SANS}; font-weight: 600;`">{{ group.label }} <span style="color: #61656C; font-weight: 400;" v-if="group.note">&middot; {{ group.note }}</span></label>
                    <div style="display: flex; align-items: flex-end; gap: 34px; margin-top: 14px;">
                        <div v-for="stat in group.stats" :key="stat.label" style="display: flex; flex-direction: column;">
                            <span :style="`color: #F7F7F7; font-size: 46px; font-family: ${MONO}; font-weight: 600; line-height: 1.05;`">
                                {{ stat.value }}<span v-if="stat.unit" :style="`font-size: 20px; color: #94979C; font-weight: 500; margin-left: 6px;`">{{ stat.unit }}</span>
                            </span>
                            <span style="display: flex; align-items: center; margin-top: 7px;">
                                <img v-if="stat.icon" :src="stat.icon" style="width: 18px; margin-right: 6px;"/>
                                <span :style="`font-size: 17px; color: #A9AEB8; font-family: ${SANS}; font-weight: 500;`">{{ stat.label }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #1D222B; padding: 16px 64px 20px;">
            <img src="/images/results/benchkit-by-server-side-up.png" style="height: 28px; display: block;"/>
            <span :style="`font-size: 17px; color: #61656C; font-family: ${MONO};`"><template v-if="host">{{ display.timestamp }} &middot; </template>#BenchKit</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { runDisplay, formatMsParts } from '@/Composables/useRunSummary';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
    sections: {
        type: Object,
        default: () => ({}),
    },
});

const MONO = "'JetBrains Mono', monospace";
const SANS = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

const display = computed(() => runDisplay(props.run));

const operations = computed(() => [
    { key: 'create', label: 'Create', data: display.value.php?.create ?? {} },
    { key: 'read', label: 'Read', data: display.value.php?.read ?? {} },
    { key: 'update', label: 'Update', data: display.value.php?.update ?? {} },
    { key: 'delete', label: 'Delete', data: display.value.php?.delete ?? {} },
]
    .filter((operation) => operation.data.milliseconds != null)
    .map((operation) => ({ ...operation, parts: formatMsParts(operation.data.milliseconds) })));

// DB read is the closest thing to a real Laravel page (query + hydrate +
// respond), so it leads; static is the framework ceiling, not the story
const HERO_ROUTES = [
    { key: 'db_read', label: 'DB read' },
    { key: 'json', label: 'JSON API' },
    { key: 'static', label: 'static' },
];

const heroRoute = computed(() => {
    for (const { key, label } of HERO_ROUTES) {
        const data = display.value.http?.routes?.[key];

        if( data?.requests_per_second != null ) {
            return { key, label, data };
        }
    }

    return null;
});

const heroNumber = computed(() => Math.round(heroRoute.value?.data.requests_per_second ?? 0).toLocaleString());

// Throughput and its full latency story — req/s alone hides what the tail
// paid for it
const heroContext = computed(() => {
    const data = heroRoute.value?.data ?? {};

    return ['p50', 'p95', 'p99']
        .filter((percentile) => data[`${percentile}_ms`] != null)
        .map((percentile) => `${percentile} ${Math.round(data[`${percentile}_ms`]).toLocaleString()}ms`)
        .join(' · ');
});


// The poster number scales down for hosts fast enough to earn extra digits
const heroFontSize = computed(() => {
    const length = heroNumber.value.length;

    if( length <= 3 ) {
        return 190;
    }

    return length <= 5 ? 155 : 122;
});

// Hosting details replace the timestamp in the corner when any are filled —
// plan_notes is the pre-split legacy field on older snapshots
const host = computed(() => {
    const meta = props.run.meta ?? {};
    const details = [meta.plan ?? meta.plan_notes, meta.datacenter, meta.cost].filter(Boolean).join(' · ');

    if( !meta.provider && !details ) {
        return null;
    }

    return {
        provider: meta.provider ?? null,
        details: details || null,
    };
});

const chips = computed(() => {
    const environment = display.value.environment;
    const http = display.value.http;

    return [
        environment.phpVersion ? { text: `PHP ${environment.phpVersion}` } : null,
        environment.laravelVersion ? { text: `Laravel ${environment.laravelVersion}` } : null,
        environment.database ? { text: environment.database } : null,
        http?.connections && http?.duration_seconds ? { text: `${http.connections} connections · ${http.duration_seconds}s` } : null,
    ].filter(Boolean);
});

const showNetwork = computed(() => display.value.network && (props.sections.network ?? true));
const showGeekbench = computed(() => display.value.geekbench && (props.sections.geekbench ?? true));

const geekbenchVersion = computed(() =>
    props.run.benchmarks?.yabs?.geekbench?.[0]?.version ?? props.run.settings?.geekbench_version ?? null);

const statGroups = computed(() => {
    const groups = [];

    if( display.value.http?.rps && operations.value.length ) {
        groups.push({
            label: 'Database CRUD',
            note: `${display.value.php.create.records ?? 100} records`,
            stats: operations.value.map((operation) => ({
                value: operation.parts.value,
                unit: operation.parts.unit,
                label: operation.label,
                icon: `/images/results/${operation.key}.png`,
            })),
        });
    }

    if( showNetwork.value ) {
        groups.push({
            label: 'Network',
            note: 'to Cloudflare',
            stats: [
                { value: parseFloat(display.value.network.download).toFixed(0), unit: 'mbps', label: 'Down', icon: '/images/results/download-cloud.png' },
                { value: parseFloat(display.value.network.upload).toFixed(0), unit: 'mbps', label: 'Up', icon: '/images/results/upload-cloud.png' },
                { value: parseFloat(display.value.network.latency).toFixed(0), unit: 'ms', label: 'Latency', icon: '/images/results/latency-switch.png' },
            ],
        });
    }

    return groups;
});
</script>
