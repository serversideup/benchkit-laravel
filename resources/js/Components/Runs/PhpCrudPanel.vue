<template>
    <PanelSection title="Laravel database performance">
        <template #aside>
            <span class="flex flex-wrap items-center gap-2">
                <Chip v-if="mode">{{ mode }} suite</Chip>
                <Chip>{{ records }} records per operation</Chip>
            </span>
        </template>

        <p class="mt-2 text-xs text-[#94979C]">&darr; Lower is better &mdash; total time per operation</p>

        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-x-6 sm:gap-x-10 gap-y-6">
            <div v-for="operation in operations" :key="operation.key" :title="`${operation.label}: ${formatMs(operation.data.milliseconds)} for ${operation.data.records ?? 100} records`">
                <p class="flex items-center gap-1.5">
                    <img :src="`/images/results/${operation.key}.png`" :alt="operation.label" class="w-4 h-4">
                    <span class="text-sm font-medium text-[#94979C]">{{ operation.label }}</span>
                </p>
                <p class="mt-1.5 text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ formatMs(operation.data.milliseconds) }}</p>
                <BarMeter class="mt-3.5 block h-2" :percent="operation.percent" />
            </div>
        </div>

        <div v-if="php.subjects?.length" class="mt-7">
            <button @click="showSubjects = !showSubjects" type="button" class="flex items-center gap-2 text-sm text-[#94979C] cursor-pointer hover:text-[#CECFD2]">
                <IconChevronDown :class="{ 'rotate-180': showSubjects }" class="transition-transform duration-200 text-[#61656C]" />
                View all {{ php.subjects.length }} raw phpbench measurements<template v-if="suiteTotal"> <span class="text-[#61656C]">&middot; {{ suiteTotal.toLocaleString() }}ms total</span></template>
            </button>

            <div v-show="showSubjects" class="mt-3 rounded-lg border border-[#22262F] overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#22262F] text-[#94979C]">
                            <th class="px-4 py-2 font-normal">Benchmark</th>
                            <th class="px-4 py-2 font-normal">Subject</th>
                            <th class="px-4 py-2 font-normal text-right">Mean</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono">
                        <tr v-for="subject in php.subjects" :key="`${subject.benchmark}-${subject.subject}`" class="border-b border-[#22262F] last:border-b-0">
                            <td class="px-4 py-2 text-[#94979C]">{{ subject.benchmark }}</td>
                            <td class="px-4 py-2 text-[#CECFD2]">{{ subject.subject }}</td>
                            <td class="px-4 py-2 text-right text-[#F7F7F7]">{{ formatMean(subject.mean_us) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </PanelSection>
</template>

<script setup>
import { computed, ref } from 'vue';
import BarMeter from '@/Components/BarMeter.vue';
import Chip from '@/Components/Chip.vue';
import PanelSection from '@/Components/PanelSection.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import { formatMs } from '@/Composables/useRunSummary';
import { suiteTotalMs } from '@/Composables/useRunComparison';

const props = defineProps({
    php: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        default: null,
    },
});

const showSubjects = ref(false);

const operations = computed(() => {
    const entries = [
        { key: 'create', label: 'Create', data: props.php.create ?? {} },
        { key: 'read', label: 'Read', data: props.php.read ?? {} },
        { key: 'update', label: 'Update', data: props.php.update ?? {} },
        { key: 'delete', label: 'Delete', data: props.php.delete ?? {} },
    ].filter((operation) => operation.data.milliseconds != null);

    const maxMs = Math.max(1, ...entries.map((operation) => operation.data.milliseconds));

    return entries.map((operation) => ({
        ...operation,
        percent: Math.max(2, (operation.data.milliseconds / maxMs) * 100),
    }));
});

const records = computed(() => operations.value[0]?.data.records ?? 100);

// Quick mode only measures the CRUD headline, where a suite total would
// just restate it — suiteTotalMs returns null there.
const suiteTotal = computed(() => suiteTotalMs(props.php));

const formatMean = (microseconds) => {
    if( microseconds == null ) {
        return '—';
    }

    return microseconds >= 1000 ? `${(microseconds / 1000).toFixed(2)}ms` : `${Math.round(microseconds)}µs`;
};
</script>
