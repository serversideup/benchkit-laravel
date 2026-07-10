import { ref, watch } from 'vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useSettings } from '@/Composables/useSettings';
import { loadHostDetails } from '@/Composables/useHostDetails';

const { queue, results, state } = useBenchmarkQueue();
const { form, activePreset } = useSettings();

const saveState = ref('idle');
const lastRunId = ref(null);
const lastRun = ref(null);

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const completedStages = () => queue.filter((benchmark) => results[benchmark].status === 'completed');

// The RIPE holder for the server's ASN is the best zero-config guess at the
// hosting provider (e.g. "DIGITALOCEAN-ASN"). Any failure — stage not run,
// endpoint down, CORS — degrades to null; the user can set it manually.
const detectProvider = async (stages) => {
    if( !stages.includes('cfspeedtest') ) {
        return null;
    }

    try {
        const network = await fetch('/cfspeedtest/results', { signal: AbortSignal.timeout(2500) }).then((response) => response.json());
        const asn = network.cfspeedtest_results?.asn;

        if( !asn ) {
            return null;
        }

        const asData = await fetch(`https://stat.ripe.net/data/as-overview/data.json?resource=AS${asn}`, { signal: AbortSignal.timeout(2500) }).then((response) => response.json());

        return asData.data?.holder ?? null;
    } catch {
        return null;
    }
};

const save = async () => {
    const stages = completedStages();

    if( stages.length === 0 ) {
        saveState.value = 'empty';
        return;
    }

    if( saveState.value === 'saving' ) {
        return;
    }

    saveState.value = 'saving';

    const logs = Object.fromEntries(stages.map((benchmark) => [benchmark, results[benchmark].output]));

    // Details the user entered on a previous run carry forward; a remembered
    // provider outranks the RIPE network guess
    const remembered = loadHostDetails();
    const provider = remembered?.provider ?? await detectProvider(stages);

    try {
        const response = await fetch('/runs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                stages_completed: stages,
                settings: form.data(),
                preset: activePreset.value,
                provider,
                provider_source: remembered?.provider ? 'user' : 'ripe',
                plan: remembered?.plan ?? null,
                datacenter: remembered?.datacenter ?? null,
                cost: remembered?.cost ?? null,
                logs,
            }),
        });

        if( !response.ok ) {
            throw new Error(`Saving the run failed (HTTP ${response.status}).`);
        }

        const data = await response.json();

        lastRunId.value = data.run.id;
        lastRun.value = data.run;
        saveState.value = 'saved';
    } catch (error) {
        console.error(error);
        saveState.value = 'failed';
    }
};

watch(state, (value) => {
    if( value === 'completed' ) {
        save();
    }

    if( value === 'running' ) {
        saveState.value = 'idle';
        lastRunId.value = null;
        lastRun.value = null;
    }
});

export const useRunSnapshot = () => {
    return {
        saveState,
        lastRunId,
        lastRun,
        retry: save,
    };
};
