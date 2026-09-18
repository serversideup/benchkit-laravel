<template>
    <div class="grid grid-cols-2 gap-x-4 gap-y-3.5" :class="gridClass">
        <div v-for="field in HOST_TEXT_FIELDS" :key="field.key" class="flex flex-col gap-1.5">
            <label :for="`${idPrefix}-${field.key}`" class="text-xs text-[#94979C]">{{ field.label }}</label>
            <SuggestInput :id="`${idPrefix}-${field.key}`" v-model="host[field.key]"
                :options="optionsFor(field)" :placeholder="field.placeholder" :max="field.max" />
        </div>

        <!-- One field, one border. The amount is the input; the currency and
             its symbol are chrome attached to it, not three controls in a row.
             "/mo" is gone because the label already says monthly. -->
        <div class="flex flex-col gap-1.5">
            <label :for="`${idPrefix}-cost_amount`" class="text-xs text-[#94979C]">Monthly cost</label>

            <div class="flex items-stretch rounded-lg border border-[#373A41] bg-[#13161B] transition-colors focus-within:border-[#61656C]">
                <span v-if="symbol" class="flex shrink-0 items-center pl-3 font-mono text-sm text-[#61656C]">{{ symbol }}</span>
                <input :id="`${idPrefix}-cost_amount`" v-model="amount" type="text" inputmode="decimal" maxlength="12" placeholder="24"
                    class="w-full min-w-0 flex-1 border-0 bg-transparent px-2.5 py-2 font-mono text-sm text-[#F7F7F7] placeholder:text-[#61656C] focus:ring-0 focus:outline-none">

                <Listbox v-model="host.cost_currency" as="div" class="relative shrink-0">
                    <ListboxButton class="flex h-full cursor-pointer items-center gap-1 rounded-r-lg px-2.5 font-mono text-sm text-[#94979C] transition-colors hover:bg-[rgba(255,255,255,0.05)] hover:text-[#CECFD2] focus:outline-none">
                        {{ host.cost_currency }}
                        <IconChevronDown :size="14" />
                    </ListboxButton>

                    <ListboxOptions class="scroll-slim absolute right-0 z-30 mt-1.5 max-h-56 w-32 overflow-auto rounded-lg border border-[#22262F] bg-[#13161B] py-1 shadow-2xl shadow-black/50 focus:outline-none">
                        <ListboxOption v-for="currency in CURRENCIES" :key="currency.code" :value="currency.code" as="template" v-slot="{ active, selected }">
                            <li class="flex cursor-pointer items-center justify-between gap-3 px-3 py-1.5 font-mono text-sm transition-colors"
                                :class="active ? 'bg-[rgba(255,255,255,0.06)] text-[#F7F7F7]' : 'text-[#CECFD2]'">
                                <span>{{ currency.code }}</span>
                                <span :class="selected ? 'text-[#E62E05]' : 'text-[#61656C]'">{{ currency.symbol }}</span>
                            </li>
                        </ListboxOption>
                    </ListboxOptions>
                </Listbox>
            </div>
        </div>

        <!-- The one thing the field cannot show: hourly billing is common and
             nobody knows the multiplier offhand. -->
        <p class="col-span-full text-xs" :class="otherPeriod ? 'text-[#F79009]' : 'text-[#61656C]'">
            {{ otherPeriod ? 'Enter the monthly price. Billed hourly? Multiply by 730.' : 'Billed hourly? Multiply by 730.' }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue';
import SuggestInput from '@/Components/SuggestInput.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import { CURRENCIES, currencySymbol, namesAnotherPeriod } from '@/cost';
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
    // Keeps input/listbox ids unique when two editors exist in the DOM
    idPrefix: {
        type: String,
        required: true,
    },
    // Column count for the fields
    gridClass: {
        type: String,
        default: '',
    },
});

// What the user has typed before comes first, then the canonical names — so
// the list nudges toward "DigitalOcean" over "digitalocean" without
// forgetting a host we've never heard of.
const optionsFor = (field) => {
    const seen = props.history[field.key] ?? [];

    return [
        ...seen.map((value) => ({ value, keywords: [] })),
        ...(field.suggestions ?? [])
            .filter((provider) => !seen.includes(provider.name))
            .map((provider) => ({ value: provider.name, keywords: provider.aliases })),
    ];
};

// A number next to "/hr" or "/yr" is not a monthly price and is not stored;
// say so rather than dropping it silently.
const otherPeriod = computed(() => namesAnotherPeriod(props.host.cost_amount));

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
