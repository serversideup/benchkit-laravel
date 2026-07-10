<template>
    <!-- A quiet two-column ledger speaking the page's Run A / Run B language:
         labels left, values right, A dim and B white — no pills, no arrows.
         Tests that were toggled read as Ran/Skipped with the same status
         dots as the run history. -->
    <div class="flex flex-col">
        <div class="grid grid-cols-[minmax(0,1fr)_8.5rem_8.5rem] gap-4 pb-2.5 border-b border-[#22262F] text-sm text-[#94979C]">
            <span></span>
            <span class="text-right">Run A</span>
            <span class="text-right">Run B</span>
        </div>

        <div class="flex flex-col divide-y divide-[#1D222B]">
            <div v-for="diff in diffs" :key="diff.label" class="grid grid-cols-[minmax(0,1fr)_8.5rem_8.5rem] gap-4 py-3.5 items-center">
                <span class="text-sm text-[#CECFD2]">{{ diff.label }}</span>

                <template v-if="diff.type === 'toggle'">
                    <span class="flex items-center justify-end gap-2">
                        <Status :status="diff.a ? 'completed' : 'skipped'" />
                        <span class="text-sm font-mono text-[#94979C]">{{ diff.a ? 'Ran' : 'Skipped' }}</span>
                    </span>
                    <span class="flex items-center justify-end gap-2">
                        <Status :status="diff.b ? 'completed' : 'skipped'" />
                        <span class="text-sm font-mono" :class="diff.b ? 'text-[#F7F7F7]' : 'text-[#94979C]'">{{ diff.b ? 'Ran' : 'Skipped' }}</span>
                    </span>
                </template>

                <template v-else>
                    <span class="text-sm font-mono text-right text-[#94979C] break-words">{{ diff.a }}</span>
                    <span class="text-sm font-mono text-right text-[#F7F7F7] break-words">{{ diff.b }}</span>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import Status from '@/Pages/Partials/Status.vue';

defineProps({
    diffs: {
        type: Array,
        required: true,
    },
});
</script>
