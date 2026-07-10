<template>
    <div class="w-full rounded-xl border border-[#22262F] bg-[#0C0E12] p-4 sm:p-5">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-[#F7F7F7]">Hosting details</p>
                <p class="text-xs text-[#94979C] mt-0.5">Saved to this run &mdash; prefilled on your next one.</p>
            </div>
            <span class="flex items-baseline gap-4 shrink-0 text-sm">
                <span v-if="saved" class="text-xs text-[#47CD89]">Saved</span>
                <button v-if="hasAnyValue" @click="confirmingClear = true" type="button" class="text-[#94979C] hover:text-[#F97066] cursor-pointer transition-colors duration-200">Clear</button>
                <button @click="emit('close')" type="button" class="text-[#94979C] hover:text-[#CECFD2] cursor-pointer">Done</button>
            </span>
        </div>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div v-for="field in FIELDS" :key="field.key" class="flex flex-col gap-1">
                <label :for="`host-${field.key}`" class="text-xs text-[#94979C]">{{ field.label }}</label>
                <input :id="`host-${field.key}`" v-model="host[field.key]" type="text" :maxlength="field.max" :placeholder="field.placeholder" :list="`host-${field.key}-options`"
                    class="rounded-lg border border-[#373A41] bg-[#13161B] px-3 py-2 text-sm text-[#F7F7F7] font-mono placeholder:text-[#61656C] focus:outline-none focus:border-[#61656C]">
                <datalist :id="`host-${field.key}-options`">
                    <option v-for="entry in history[field.key]" :key="entry" :value="entry" />
                </datalist>
            </div>
        </div>

        <ConfirmModal :open="confirmingClear"
            title="Clear hosting details?"
            message="This removes the host, plan, datacenter, and cost from this run — and from the prefill for your next one. Your autocomplete suggestions are kept."
            confirm-label="Clear details"
            @confirm="clearHost()" @close="confirmingClear = false" />
    </div>
</template>

<script setup>
import { computed, reactive, watch, ref } from 'vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { updateRunMeta } from '@/Composables/useRunActions';
import { saveHostDetails, loadHostHistory } from '@/Composables/useHostDetails';

const props = defineProps({
    runId: {
        type: String,
        required: true,
    },
    meta: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['updated', 'close']);

const FIELDS = [
    { key: 'provider', label: 'Host', placeholder: 'DigitalOcean', max: 120 },
    { key: 'plan', label: 'Plan', placeholder: 'Premium AMD 2GB', max: 120 },
    { key: 'datacenter', label: 'Datacenter', placeholder: 'NYC3', max: 120 },
    { key: 'cost', label: 'Monthly cost', placeholder: '$24/mo', max: 60 },
];

const saved = ref(false);
const history = loadHostHistory();

const host = reactive({
    provider: props.meta.provider ?? '',
    plan: props.meta.plan ?? props.meta.plan_notes ?? '',
    datacenter: props.meta.datacenter ?? '',
    cost: props.meta.cost ?? '',
});

const hasAnyValue = computed(() => Boolean(host.provider || host.plan || host.datacenter || host.cost));
const confirmingClear = ref(false);

const clearHost = () => {
    host.provider = '';
    host.plan = '';
    host.datacenter = '';
    host.cost = '';
    confirmingClear.value = false;
};

// Autosave on pause: persists to the run and becomes the remembered default
// for the next one. Clearing the run also clears the remembered default,
// but autocomplete history keeps past values as suggestions.
let timer = null;

watch(() => `${host.provider}|${host.plan}|${host.datacenter}|${host.cost}`, () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        try {
            const run = await updateRunMeta(props.runId, {
                provider: host.provider || null,
                plan: host.plan || null,
                datacenter: host.datacenter || null,
                cost: host.cost || null,
            });

            saveHostDetails(host);
            emit('updated', run.meta);
            saved.value = true;
            setTimeout(() => saved.value = false, 2000);
        } catch (error) {
            console.error(error);
        }
    }, 600);
});
</script>
