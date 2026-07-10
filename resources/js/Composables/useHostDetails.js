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
