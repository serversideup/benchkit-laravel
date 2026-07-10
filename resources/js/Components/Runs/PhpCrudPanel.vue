<template>
    <section class="py-9">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="text-base font-semibold text-[#F7F7F7]">Laravel database performance</h2>
            <span class="flex items-center gap-2">
                <span v-if="mode" class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">{{ mode }} suite</span>
                <span class="rounded-full border border-[#373A41] px-2.5 py-1 text-xs font-mono text-[#CECFD2]">{{ records }} records per operation</span>
            </span>
        </div>

        <p class="mt-2 text-xs text-[#94979C]">&darr; Lower is better &mdash; total time per operation</p>

        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-x-10 gap-y-6">
            <div v-for="operation in operations" :key="operation.key" :title="`${operation.label}: ${formatMs(operation.data.milliseconds)} for ${operation.data.records ?? 100} records`">
                <p class="flex items-center gap-1.5">
                    <img :src="`/images/results/${operation.key}.png`" :alt="operation.label" class="w-4 h-4">
                    <span class="text-sm font-medium text-[#94979C]">{{ operation.label }}</span>
                </p>
                <p class="mt-1.5 text-4xl text-[#F7F7F7] font-mono font-medium leading-none">{{ formatMs(operation.data.milliseconds) }}</p>
                <span class="mt-3.5 block h-2 rounded-sm bg-[#22262F] overflow-hidden">
                    <span class="block h-full rounded-r-[4px] bg-[#94979C]" :style="`width: ${operation.percent}%;`"></span>
                </span>
            </div>
        </div>

        <div v-if="php.subjects?.length" class="mt-7">
            <button @click="showSubjects = !showSubjects" type="button" class="flex items-center gap-2 text-sm text-[#94979C] cursor-pointer hover:text-[#CECFD2]">
                <svg :class="{ 'rotate-180': showSubjects }" class="transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="none">
                    <path d="M5 7.5L10 12.5L15 7.5" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                View all {{ php.subjects.length }} raw phpbench measurements<template v-if="suite"> <span class="text-[#61656C]">&middot; {{ suite.totalMs.toLocaleString() }}ms total</span></template>
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
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatMs } from '@/Composables/useRunSummary';

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

// Total mean across every phpbench subject — the same subjects run every
// full suite, so the total is comparable between runs. Quick mode only
// measures the CRUD headline, where a total would just restate it.
const suite = computed(() => {
    const subjects = props.php.subjects ?? [];

    if( subjects.length <= 4 ) {
        return null;
    }

    return {
        count: subjects.length,
        totalMs: Math.round(subjects.reduce((sum, subject) => sum + (subject.mean_us ?? 0), 0) / 1000),
    };
});

const formatMean = (microseconds) => {
    if( microseconds == null ) {
        return '—';
    }

    return microseconds >= 1000 ? `${(microseconds / 1000).toFixed(2)}ms` : `${Math.round(microseconds)}µs`;
};
</script>
