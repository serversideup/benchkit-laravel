<template>
    <div class="flex items-start gap-3 rounded-xl border p-4"
        :class="clean.ok ? 'border-[#47CD89]/25 bg-[#47CD89]/[0.04]' : 'border-[#22262F]'">
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="mt-px size-4 shrink-0"
            :class="clean.ok ? 'text-[#47CD89]' : 'text-[#61656C]'">
            <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5" />
            <path v-if="clean.ok" d="m6.75 10.25 2.25 2.25 4.25-4.75" stroke="currentColor" stroke-width="1.5"
                stroke-linecap="round" stroke-linejoin="round" />
            <circle v-else cx="10" cy="10" r="2.5" fill="currentColor" />
        </svg>

        <div class="min-w-0">
            <p class="text-sm font-medium" :class="clean.ok ? 'text-[#47CD89]' : 'text-[#CECFD2]'">
                {{ clean.ok ? 'This is a clean run' : title }}
            </p>
            <p class="mt-1 max-w-[62ch] text-sm text-[#94979C] leading-relaxed">
                <template v-if="clean.ok">
                    Nothing about how this was measured undermines the numbers, so they can sit next to anybody
                    else's. The gallery marks it, and you can filter to runs like it.
                </template>
                <template v-else>
                    {{ reasonSentence }} It is still a real measurement of this machine — it just carries something
                    that stops it being compared like for like, and the notes below say what.
                </template>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { cleanRun } from '@shared/run/clean.mjs';

/**
 * Whether this run is comparable with anybody else's, answered before someone
 * spends a submission finding out.
 *
 * The rules come from the same module the gallery uses rather than a copy, so
 * a run cannot be told one thing here and marked another there.
 */
const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
});

const clean = computed(() => cleanRun(props.run));

const title = computed(() => clean.value.reasons.length === 1
    ? 'One thing keeps this from being a clean run'
    : `${clean.value.reasons.length} things keep this from being a clean run`);

/** "A, B and C" — a list a person would read aloud. */
const reasonSentence = computed(() => {
    const reasons = clean.value.reasons;
    const list = reasons.length <= 1
        ? reasons[0]
        : `${reasons.slice(0, -1).join(', ')} and ${reasons[reasons.length - 1]}`;

    return `${list.charAt(0).toUpperCase()}${list.slice(1)}.`;
});
</script>
