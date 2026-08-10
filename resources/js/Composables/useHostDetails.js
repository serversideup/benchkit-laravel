import { computed, nextTick, reactive, ref, watch } from 'vue';
import { updateRunMeta } from '@/Composables/useRunActions';
import { DEFAULT_CURRENCY, buildCost, normalizeCost } from '@/cost';

// Remembers the user's hosting details between runs (same pattern as
// useSettings): the last-used set is injected into the next run's snapshot,
// and every distinct past value feeds per-field autocomplete so switching
// between plans or hosts is a pick, not a retype.
const STORAGE_KEY = 'benchkit-host-details';
const VERSION = 3;
const TEXT_FIELDS = ['provider', 'plan', 'datacenter'];
const FIELDS = [...TEXT_FIELDS, 'cost_amount', 'cost_currency'];
const HISTORY_LIMIT = 6;

// Seeds the provider datalist so the common answers are a pick rather than a
// free-text guess. Every distinct spelling of "DigitalOcean" that gets typed
// becomes its own filter chip in the public gallery, so nudging people onto a
// canonical name here is worth more than it looks.
export const KNOWN_PROVIDERS = [
    'AWS',
    'Akamai',
    'DigitalOcean',
    'Fly.io',
    'Google Cloud',
    'Hetzner',
    'Hostinger',
    'Laravel Cloud',
    'Linode',
    'OVH',
    'Oracle Cloud',
    'Railway',
    'Render',
    'Scaleway',
    'Self-Hosted',
    'Vultr',
];

// v2 stored cost as one free-text string ("$24/mo", "20 EUR"), which made it
// unusable for comparison and rendered euros with a dollar sign. Split it.
const migrate = (data) => {
    if( data.version === VERSION ) {
        return data;
    }

    const last = data.version === 1 ? data : (data.last ?? {});
    const cost = normalizeCost(last.cost);

    return {
        version: VERSION,
        last: {
            provider: last.provider ?? null,
            plan: last.plan ?? null,
            datacenter: last.datacenter ?? null,
            cost_amount: cost ? String(cost.amount) : null,
            cost_currency: cost?.currency ?? null,
        },
        // Free-text cost history has nothing to autocomplete against now.
        history: Object.fromEntries(TEXT_FIELDS.map((field) => [field, data.history?.[field] ?? []])),
    };
};

const read = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if( !raw ) {
            return null;
        }

        const data = JSON.parse(raw);

        if( ![1, 2, VERSION].includes(data.version) ) {
            return null;
        }

        return migrate(data);
    } catch {
        return null;
    }
};

/** The remembered details in the shape the API takes for a new run. */
export const loadHostDetails = () => {
    const data = read();

    if( !data ) {
        return null;
    }

    return {
        provider: data.last?.provider ?? null,
        plan: data.last?.plan ?? null,
        datacenter: data.last?.datacenter ?? null,
        cost: buildCost(data.last?.cost_amount, data.last?.cost_currency),
    };
};

export const loadHostHistory = () => {
    const history = read()?.history ?? {};

    return Object.fromEntries(TEXT_FIELDS.map((field) => [field, history[field] ?? []]));
};

// Everything except cost is free text with autocomplete; cost gets its own
// control because a price is a number and a currency, not a sentence.
export const HOST_TEXT_FIELDS = [
    { key: 'provider', label: 'Host', placeholder: 'DigitalOcean', max: 120, suggestions: KNOWN_PROVIDERS },
    { key: 'plan', label: 'Plan', placeholder: 'Premium AMD 2GB', max: 120 },
    { key: 'datacenter', label: 'Datacenter', placeholder: 'NYC3', max: 120 },
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

    const host = reactive({ provider: '', plan: '', datacenter: '', cost_amount: '', cost_currency: DEFAULT_CURRENCY });
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

        // Runs saved before cost was structured still hold a free-text string.
        const cost = normalizeCost(meta.cost);
        host.cost_amount = cost ? String(cost.amount) : '';
        host.cost_currency = cost?.currency ?? DEFAULT_CURRENCY;

        nextTick(() => seeding = false);
    };

    seed(meta);

    const costPayload = () => buildCost(host.cost_amount, host.cost_currency);

    const hasAnyValue = computed(() => Boolean(host.provider || host.plan || host.datacenter || host.cost_amount));

    const clearHost = () => {
        host.provider = '';
        host.plan = '';
        host.datacenter = '';
        host.cost_amount = '';
        host.cost_currency = DEFAULT_CURRENCY;
    };

    let timer = null;

    // Reactive so a caller can hold off on work that depends on the stored run
    // — the submit flow rebuilds its token from the server, and doing that
    // against a half-typed host name would publish a half-typed host name.
    const pending = ref(false);

    const persist = async () => {
        pending.value = false;
        clearTimeout(timer);
        timer = null;

        onFlush?.();

        try {
            const run = await updateRunMeta(resolveRunId(), {
                provider: host.provider || null,
                plan: host.plan || null,
                datacenter: host.datacenter || null,
                cost: costPayload(),
            });

            saveHostDetails(host);
            saved.value = true;
            setTimeout(() => saved.value = false, 2000);
            onSaved?.(run.meta);
        } catch (error) {
            console.error(error);
        }
    };

    watch(() => FIELDS.map((field) => host[field]).join('|'), () => {
        if( seeding || !active() ) {
            return;
        }

        pending.value = true;
        clearTimeout(timer);
        timer = setTimeout(persist, 600);
    });

    /**
     * Commit a pending edit now rather than waiting out the debounce. The
     * submission document is built server-side from the stored run, so an edit
     * still sitting in the timer would simply be missing from it.
     */
    const flush = async () => {
        if( pending.value ) {
            await persist();
        }
    };

    return { host, history, saved, pending, hasAnyValue, clearHost, seed, costPayload, flush };
};

export const saveHostDetails = (details) => {
    try {
        const existing = read();
        const history = { ...(existing?.history ?? {}) };

        TEXT_FIELDS.forEach((field) => {
            const value = (details[field] ?? '').trim();

            if( value ) {
                history[field] = [value, ...(history[field] ?? []).filter((entry) => entry !== value)].slice(0, HISTORY_LIMIT);
            }
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            version: VERSION,
            last: Object.fromEntries(FIELDS.map((field) => [field, String(details[field] ?? '').trim() || null])),
            history,
        }));
    } catch {
        // Storage unavailable (private mode) — details just aren't remembered
    }
};
