<template>
    <section v-if="caveats.length || http" class="rounded-xl border border-[#22262F] bg-[#0C0E12]">
        <!-- The verdict is a panel heading, not a line of status text. This is
             the one thing that has to be read before any number below it is
             worth trusting, so it carries the same weight as the panels it
             qualifies. -->
        <header class="px-6 pt-6 pb-6 sm:px-8 sm:pt-8" :class="caveats.length ? 'border-b border-[#22262F]' : ''">
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <!-- The eyebrow rule is this panel's one accent, and
                             here it is also the status. -->
                        <span aria-hidden="true" class="h-px w-6" :class="clean.ok ? 'bg-[#47CD89]' : 'bg-[#E62E05]/70'"></span>
                        <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-[#61656C]">Run quality</span>
                    </div>

                    <h2 class="mt-3 text-xl font-semibold leading-tight tracking-[-0.02em] text-[#F7F7F7] sm:text-2xl">
                        {{ clean.ok ? 'Clean run' : '⚠️ Not a clean run' }}
                    </h2>
                </div>

                <span v-if="caveats.length" class="font-mono text-[11px] uppercase tracking-[0.16em] text-[#61656C] sm:mt-9">{{ tally }}</span>
            </div>

            <p class="mt-3 max-w-[62ch] text-sm leading-relaxed text-[#94979C]">{{ summary }}</p>
        </header>

        <div v-for="(caveat, index) in caveats" :key="caveat.key"
            class="flex gap-3.5 px-6 py-5 sm:px-8" :class="index > 0 ? 'border-t border-[#22262F]' : ''">
            <!-- Colour lives in a dot, not behind the paragraph. A tinted box
                 per caveat turned a page of context into a page of alarms. -->
            <span aria-hidden="true" class="mt-1.5 size-2 shrink-0 rounded-full" :class="DOTS[caveat.severity]"></span>

            <div class="min-w-0 grow">
                <p class="text-sm font-medium text-[#F7F7F7]">{{ caveat.title }}</p>
                <p class="mt-1 max-w-[62ch] text-sm leading-relaxed text-[#94979C]">{{ caveat.detail }}</p>

                <!-- Something is wrong and there is a way out: a raised card
                     with the command ready to copy, so the remedy is the part
                     of the row you cannot miss. -->
                <div v-if="caveat.fix && caveat.severity !== 'note'"
                    class="mt-3.5 flex max-w-[62ch] items-center gap-3 rounded-lg border border-[#22262F] bg-[#13161B] py-2 pl-3.5 pr-2">
                    <p class="grow text-sm leading-relaxed text-[#CECFD2]">
                        <span v-for="(part, i) in segments(caveat.fix)" :key="i"
                            :class="part.code ? 'font-mono text-[13px] text-[#F7F7F7]' : ''">{{ part.value }}</span>
                    </p>
                    <CopyButton v-if="caveat.command" :text="caveat.command" :label="`Copy ${caveat.command}`" />
                </div>

                <!-- Nothing is wrong here, so the suggestion stays quiet. -->
                <p v-else-if="caveat.fix" class="mt-2.5 max-w-[62ch] border-l border-[#22262F] pl-3 text-sm leading-relaxed text-[#CECFD2]">
                    <span v-for="(part, i) in segments(caveat.fix)" :key="i"
                        :class="part.code ? 'font-mono text-[13px] text-[#F7F7F7]' : ''">{{ part.value }}</span>
                </p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { cleanRun } from '@shared/run/clean.mjs';
import { runCaveats } from '@/Composables/useRunCaveats';
import CopyButton from '@/Components/CopyButton.vue';

/**
 * Whether this run is comparable with anybody else's, and everything that
 * changes how its numbers should be read — one panel, above the results,
 * because a run gets screenshotted and quoted from the top.
 *
 * The rules themselves live in useRunCaveats, because the submit flow gates on
 * the same list. A run cannot be told one thing here and judged by another
 * when it is offered to the gallery.
 */
const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
    http: {
        type: Object,
        default: null,
    },
});

/**
 * The same rules the gallery uses, not a copy, so a run cannot be told one
 * thing here and marked another there.
 */
const clean = computed(() => cleanRun(props.run));

const caveats = computed(() => runCaveats({ environment: props.run?.environment, http: props.http }));

// Red means the numbers are wrong, amber that they are right and easy to
// misread, grey that it is context. A healthy run should not look like a list
// of failures.
const DOTS = {
    high: 'bg-[#F97066]',
    medium: 'bg-[#F79009]',
    note: 'bg-[#61656C]',
};

/** Backtick-delimited spans in a fix render in the mono voice. */
const segments = (text) => String(text)
    .split('`')
    .map((value, index) => ({ value, code: index % 2 === 1 }));

/** "3 issues \u00b7 2 notes" — the shape of the list before you read it. */
const issueCount = computed(() => caveats.value.filter((caveat) => caveat.severity !== 'note').length);

const tally = computed(() => {
    const notes = caveats.value.length - issueCount.value;

    return [
        issueCount.value ? `${issueCount.value} ${issueCount.value === 1 ? 'issue' : 'issues'}` : null,
        notes ? `${notes} ${notes === 1 ? 'note' : 'notes'}` : null,
    ].filter(Boolean).join(' \u00b7 ');
});

/**
 * What the verdict means for the reader, in one line. A run can fail the clean
 * check on measurement alone, with nothing wrong in the list to fix, so this
 * asks about the issues rather than about the verdict.
 */
const summary = computed(() => {
    if (clean.value.ok) {
        return 'Nothing here undermines the numbers, so they sit next to anybody else\'s.';
    }

    return issueCount.value > 0
        ? 'Work through these items and run again to compare this run with other hosts.'
        : 'The numbers are sound. How they were measured is what stops a like-for-like comparison.';
});
</script>
