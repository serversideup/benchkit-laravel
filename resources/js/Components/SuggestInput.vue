<template>
    <Combobox :model-value="modelValue" @update:model-value="pick" nullable>
        <div class="relative">
            <ComboboxInput :id="id" :placeholder="placeholder" :maxlength="max" :display-value="(value) => value ?? ''"
                autocomplete="off" @change="type($event.target.value)"
                class="w-full rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 font-mono text-sm text-[#F7F7F7] transition-colors placeholder:text-[#61656C] focus:border-[#61656C] focus:ring-0 focus:outline-none"
                :class="options.length ? 'pr-9' : ''" />

            <ComboboxButton v-if="options.length" class="absolute inset-y-0 right-0 flex cursor-pointer items-center px-2.5 text-[#61656C] transition-colors hover:text-[#CECFD2]">
                <IconChevronDown :size="16" />
            </ComboboxButton>

            <!-- Anchored to the field rather than the page, so it tracks the
                 grid cell it belongs to at any width. -->
            <ComboboxOptions v-if="matches.length"
                class="scroll-slim absolute z-30 mt-1.5 max-h-56 w-full overflow-auto rounded-lg border border-[#22262F] bg-[#13161B] py-1 shadow-2xl shadow-black/50 focus:outline-none">
                <ComboboxOption v-for="option in matches" :key="option.value" :value="option.value" as="template" v-slot="{ active, selected }">
                    <li class="flex cursor-pointer items-center justify-between gap-3 px-3 py-1.5 font-mono text-sm transition-colors"
                        :class="active ? 'bg-[rgba(255,255,255,0.06)] text-[#F7F7F7]' : 'text-[#CECFD2]'">
                        {{ option.value }}
                        <IconCheck v-if="selected" class="shrink-0 text-[#E62E05]" />
                    </li>
                </ComboboxOption>
            </ComboboxOptions>
        </div>
    </Combobox>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';

/**
 * A text field that suggests without insisting.
 *
 * The names we know are a shortcut, never a constraint — a host we have never
 * heard of has to be as easy to enter as DigitalOcean, so anything typed is
 * kept verbatim and the list simply stops matching.
 *
 * Replaces a native <datalist>, which renders the operating system's own
 * dropdown: unstyleable, differently shaped in every browser, and wearing a
 * blue focus ring that belongs to no part of this app.
 */
const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    // Strings, or { value, keywords } when a name has alternate spellings
    options: {
        type: Array,
        default: () => [],
    },
    id: {
        type: String,
        required: true,
    },
    placeholder: {
        type: String,
        default: '',
    },
    max: {
        type: Number,
        default: 120,
    },
});

const emit = defineEmits(['update:modelValue']);

// What was typed, kept apart from the committed value so the list filters as
// they type without the field fighting them for control of its own contents.
const query = ref(null);

// A plain string is its own only keyword; the object form carries alternate
// spellings that match without ever being shown or stored.
const normalised = computed(() => props.options.map((option) => typeof option === 'string'
    ? { value: option, keywords: [] }
    : option));

const matches = computed(() => {
    const needle = (query.value ?? '').trim().toLowerCase();

    if (!needle) {
        return normalised.value;
    }

    return normalised.value.filter(({ value, keywords }) => [value, ...(keywords ?? [])]
        .some((term) => term.toLowerCase().includes(needle)));
});

const type = (value) => {
    query.value = value;
    emit('update:modelValue', value);
};

const pick = (value) => {
    query.value = null;
    emit('update:modelValue', value ?? '');
};
</script>
