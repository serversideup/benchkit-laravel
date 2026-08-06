<template>
    <div>
        <div class="grid grid-cols-2 gap-3.5" :class="gridClass">
            <div v-for="field in HOST_TEXT_FIELDS" :key="field.key" class="flex flex-col gap-1">
                <label :for="`${idPrefix}-${field.key}`" class="text-xs text-[#94979C]">
                    {{ field.label }} <span class="text-[#61656C]">(optional)</span>
                </label>
                <input :id="`${idPrefix}-${field.key}`" v-model="host[field.key]" type="text" :maxlength="field.max" :placeholder="field.placeholder" :list="`${idPrefix}-${field.key}-options`"
                    class="rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 text-sm text-[#F7F7F7] font-mono placeholder:text-[#61656C] focus:outline-none focus:border-[#61656C]">
                <datalist :id="`${idPrefix}-${field.key}-options`">
                    <option v-for="entry in optionsFor(field)" :key="entry" :value="entry" />
                </datalist>
            </div>
        </div>

        <!-- Cost is a number and a currency, never a sentence — the field
             frames the unit so nobody has to type "$24/mo" and hope. -->
        <div class="mt-3.5 flex flex-col gap-1">
            <label :for="`${idPrefix}-cost_amount`" class="text-xs text-[#94979C]">
                Monthly cost <span class="text-[#61656C]">(optional)</span>
            </label>
            <div class="flex items-stretch rounded-lg border border-[#373A41] bg-[#13161B] focus-within:border-[#61656C]">
                <select :id="`${idPrefix}-cost_currency`" v-model="host.cost_currency" aria-label="Currency"
                    class="cursor-pointer rounded-l-lg border-r border-[#373A41] bg-transparent py-2 pl-3 pr-2 text-sm font-mono text-[#CECFD2] focus:outline-none">
                    <option v-for="currency in CURRENCIES" :key="currency.code" :value="currency.code" class="bg-[#13161B]">{{ currency.code }}</option>
                </select>
                <div class="flex min-w-0 flex-1 items-center gap-1.5 px-3">
                    <span v-if="symbol" class="shrink-0 text-sm font-mono text-[#61656C]">{{ symbol }}</span>
                    <input :id="`${idPrefix}-cost_amount`" v-model="amount" type="text" inputmode="decimal" maxlength="12" placeholder="24"
                        class="w-full min-w-0 bg-transparent py-2 text-sm font-mono text-[#F7F7F7] placeholder:text-[#61656C] focus:outline-none">
                    <span class="shrink-0 text-sm font-mono text-[#61656C]">/mo</span>
                </div>
            </div>
            <p class="text-xs text-[#61656C]">What this server costs per month, in the currency you're billed. Billed hourly? Multiply by 730.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CURRENCIES, currencySymbol } from '@/cost';
import { HOST_TEXT_FIELDS } from '@/Composables/useHostDetails';

const props = defineProps({
    // The reactive host object from useHostEditor — inputs bind straight
    // into it
    host: {
        type: Object,
        required: true,
    },
    history: {
        type: Object,
        required: true,
    },
    // Keeps input/datalist ids unique when two editors exist in the DOM
    idPrefix: {
        type: String,
        required: true,
    },
    // Column count for the text fields; the cost row is always full width
    gridClass: {
        type: String,
        default: '',
    },
});

// What the user has typed before comes first, then the canonical names — so
// the list nudges toward "DigitalOcean" over "digitalocean" without
// forgetting a host we've never heard of.
const optionsFor = (field) => [
    ...(props.history[field.key] ?? []),
    ...(field.suggestions ?? []).filter((name) => !(props.history[field.key] ?? []).includes(name)),
];

// Codes like CHF are their own symbol — showing it twice reads as a typo.
const symbol = computed(() => {
    const value = currencySymbol(props.host.cost_currency);

    return value === props.host.cost_currency ? '' : value;
});

// Filtering as they type means a malformed price can't reach the run at all,
// which beats validating it after the fact and beats parsing it later.
const amount = computed({
    get: () => props.host.cost_amount,
    set: (value) => {
        const [whole, ...rest] = String(value).replace(/[^\d.]/g, '').split('.');

        props.host.cost_amount = rest.length ? `${whole}.${rest.join('').slice(0, 2)}` : whole;
    },
});
</script>
