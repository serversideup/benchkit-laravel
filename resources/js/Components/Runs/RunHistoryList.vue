<template>
    <div class="flex flex-col gap-3">
        <div v-for="run in runs" :key="run.id"
            class="rounded-xl border bg-[#0C0E12] p-4 sm:p-5 flex items-center gap-4 transition-colors duration-150"
            :class="isSelected(run.id) ? 'border-[#E62E05]' : 'border-[#22262F]'">
            <input v-if="selectable" type="checkbox" :checked="isSelected(run.id)" @change="toggleSelection(run.id)"
                :disabled="!isSelected(run.id) && selected.length >= 2"
                class="shrink-0 h-4 w-4 rounded border-[#373A41] bg-[#13161B] text-[#E62E05] focus:ring-[#E62E05] focus:ring-offset-0 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">

            <Link :href="`/runs/${run.id}`" class="flex-1 min-w-0 group cursor-pointer">
                <p class="text-base text-[#F7F7F7] font-mono truncate group-hover:underline underline-offset-4 decoration-[#373A41]">{{ run.meta.label }}</p>
                <p class="mt-0.5 text-xs text-[#94979C] font-mono truncate">
                    {{ formatUTCTimestamp(run.created_at) }}
                    <template v-if="run.meta.provider"> &middot; {{ run.meta.provider }}</template>
                    <template v-if="hostDetails(run.meta)"> &middot; {{ hostDetails(run.meta) }}</template>
                </p>
            </Link>

            <div class="hidden sm:flex items-center gap-5 font-mono shrink-0">
                <div class="text-right w-16">
                    <template v-if="run.summary.http_rps">
                        <p class="text-lg text-[#F7F7F7] font-medium leading-tight">{{ Math.round(run.summary.http_rps).toLocaleString() }}</p>
                        <p class="text-[10px] text-[#61656C]">req/s</p>
                    </template>
                </div>
                <div class="text-right w-20">
                    <template v-if="run.summary.php_create_ms != null">
                        <p class="text-lg text-[#F7F7F7] font-medium leading-tight">{{ formatMs(run.summary.php_create_ms) }}</p>
                        <p class="text-[10px] text-[#61656C]">create 100</p>
                    </template>
                </div>
            </div>

            <!-- Every stage renders in a fixed order — skipped ones as quiet
                 dots — so the status column aligns across every row -->
            <div class="flex items-center gap-1 shrink-0">
                <Tooltip v-for="stage in STAGES" :key="stage.key"
                    :label="`${stage.label} — ${run.stages_completed.includes(stage.key) ? 'completed' : 'skipped'}`">
                    <Status :status="run.stages_completed.includes(stage.key) ? 'completed' : 'skipped'" />
                </Tooltip>
            </div>

            <button v-if="deletable" @click="pendingDelete = run" type="button" class="shrink-0 p-1.5 rounded-md text-[#61656C] hover:text-[#F97066] hover:bg-[rgba(255,255,255,0.06)] cursor-pointer" :title="`Delete ${run.meta.label}`">
                <span class="sr-only">Delete run</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <path d="M13.333 5v-.667c0-.933 0-1.4-.181-1.756a1.667 1.667 0 0 0-.729-.729c-.356-.181-.823-.181-1.756-.181h-1.334c-.933 0-1.4 0-1.756.181-.314.16-.569.415-.729.729-.181.356-.181.823-.181 1.756V5m1.666 4.583v4.167m3.334-4.167v4.167M2.5 5h15m-1.667 0v9.333c0 1.4 0 2.1-.272 2.635a2.5 2.5 0 0 1-1.093 1.093c-.535.272-1.235.272-2.635.272H8.167c-1.4 0-2.1 0-2.635-.272a2.5 2.5 0 0 1-1.093-1.093c-.272-.535-.272-1.235-.272-2.635V5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <ConfirmModal :open="pendingDelete !== null"
            :title="`Delete “${pendingDelete?.meta.label}”?`"
            message="This permanently removes the run and its results — there's no undo."
            confirm-label="Delete run"
            @confirm="deleteConfirmed()" @close="pendingDelete = null" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Status from '@/Pages/Partials/Status.vue';
import Tooltip from '@/Components/Tooltip.vue';
import { formatUTCTimestamp, formatMs, hostDetailsLine } from '@/Composables/useRunSummary';
import { deleteRunAndRefresh } from '@/Composables/useRunActions';
import { STAGES } from '@/stages';

const props = defineProps({
    runs: {
        type: Array,
        required: true,
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    deletable: {
        type: Boolean,
        default: false,
    },
    selected: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:selected']);

const isSelected = (id) => props.selected.includes(id);

const hostDetails = (meta) => hostDetailsLine(meta, ['plan', 'datacenter', 'cost']);

const toggleSelection = (id) => {
    const next = isSelected(id)
        ? props.selected.filter((selectedId) => selectedId !== id)
        : [...props.selected, id];

    emit('update:selected', next);
};

const pendingDelete = ref(null);

const deleteConfirmed = async () => {
    const run = pendingDelete.value;
    pendingDelete.value = null;

    try {
        await deleteRunAndRefresh(run.id);
    } catch (error) {
        console.error(error);
    }
};
</script>
