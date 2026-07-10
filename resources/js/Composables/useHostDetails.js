import { computed, nextTick, reactive, ref, watch } from 'vue';
import { updateRunMeta } from '@/Composables/useRunActions';

// Remembers the user's hosting details between runs (same pattern as
// useSettings): the last-used set is injected into the next run's snapshot,
// and every distinct past value feeds per-field autocomplete so switching
// between plans or hosts is a pick, not a retype.
const STORAGE_KEY = 'benchkit-host-details';
const VERSION = 2;
const FIELDS = ['provider', 'plan', 'datacenter', 'cost'];
const HISTORY_LIMIT = 6;

const read = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if( !raw ) {
            return null;
        }

        const data = JSON.parse(raw);

        if( data.version === VERSION ) {
            return data;
        }

        if( data.version === 1 ) {
            return {
                version: VERSION,
                last: Object.fromEntries(FIELDS.map((field) => [field, data[field] ?? null])),
                history: {},
            };
        }

        return null;
    } catch {
        return null;
    }
};

export const loadHostDetails = () => {
    const data = read();

    if( !data ) {
        return null;
    }

    return Object.fromEntries(FIELDS.map((field) => [field, data.last?.[field] ?? null]));
};

export const loadHostHistory = () => {
    const history = read()?.history ?? {};

    return Object.fromEntries(FIELDS.map((field) => [field, history[field] ?? []]));
};

export const HOST_FIELDS = [
    { key: 'provider', label: 'Host', placeholder: 'DigitalOcean', max: 120 },
    { key: 'plan', label: 'Plan', placeholder: 'Premium AMD 2GB', max: 120 },
    { key: 'datacenter', label: 'Datacenter', placeholder: 'NYC3', max: 120 },
    { key: 'cost', label: 'Monthly cost', placeholder: '$24/mo', max: 60 },
];

/**
 * One host-details editor, shared by the run page panel and the share modal:
 * a reactive field set seeded from run meta that autosaves to the run when
 * typing pauses (600ms) and becomes the remembered prefill for the next run.
 *
 * `active` gates the autosave (e.g. only while the share modal is open),
 * `onFlush` runs when a typing pause is committed (before the request), and
 * `onSaved` receives the persisted meta from the server.
 */
export const useHostEditor = ({ runId, meta = {}, active = () => true, onFlush = null, onSaved = null }) => {
    const resolveRunId = typeof runId === 'function' ? runId : () => runId;

    const host = reactive({ provider: '', plan: '', datacenter: '', cost: '' });
    const history = loadHostHistory();
    const saved = ref(false);

    // Seeding must not trigger the autosave — the flag stays up until the
    // watcher (pre-flush) has run, and nextTick clears it even when the
    // seeded values are unchanged and the watcher never fires
    let seeding = false;

    const seed = (meta = {}) => {
        seeding = true;
        host.provider = meta.provider ?? '';
        host.plan = meta.plan ?? meta.plan_notes ?? '';
        host.datacenter = meta.datacenter ?? '';
        host.cost = meta.cost ?? '';
        nextTick(() => seeding = false);
    };

    seed(meta);

    const hasAnyValue = computed(() => Boolean(host.provider || host.plan || host.datacenter || host.cost));

    const clearHost = () => {
        host.provider = '';
        host.plan = '';
        host.datacenter = '';
        host.cost = '';
    };

    let timer = null;

    watch(() => `${host.provider}|${host.plan}|${host.datacenter}|${host.cost}`, () => {
        if( seeding || !active() ) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(async () => {
            onFlush?.();

            try {
                const run = await updateRunMeta(resolveRunId(), {
                    provider: host.provider || null,
                    plan: host.plan || null,
                    datacenter: host.datacenter || null,
                    cost: host.cost || null,
                });

                saveHostDetails(host);
                saved.value = true;
                setTimeout(() => saved.value = false, 2000);
                onSaved?.(run.meta);
            } catch (error) {
                console.error(error);
            }
        }, 600);
    });

    return { host, history, saved, hasAnyValue, clearHost, seed };
};

export const saveHostDetails = (details) => {
    try {
        const existing = read();
        const history = { ...(existing?.history ?? {}) };

        FIELDS.forEach((field) => {
            const value = (details[field] ?? '').trim();

            if( value ) {
                history[field] = [value, ...(history[field] ?? []).filter((entry) => entry !== value)].slice(0, HISTORY_LIMIT);
            }
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            version: VERSION,
            last: Object.fromEntries(FIELDS.map((field) => [field, (details[field] ?? '').trim() || null])),
            history,
        }));
    } catch {
        // Storage unavailable (private mode) — details just aren't remembered
    }
};
