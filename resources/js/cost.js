// Hosting cost as structured data rather than free text.
//
// Cost only earns its place in a run if you can compare it — "req/s per
// dollar" is the whole point — and that needs a number, a currency, and a
// single period. So a cost is always:
//
//     { amount: 24, currency: 'USD', period: 'monthly' }
//
// Period is fixed at monthly: one unit means no conversion logic and no
// ambiguity about what a number means. Currency is stored as the submitter
// actually pays it and is never converted here — turning EUR into USD is a
// presentation concern that depends on a rate and a date, and baking it into
// stored data makes the data wrong the moment rates move.
//
// The PHP mirror of this lives in app/Support/HostCost.php, and the docs site
// has its own copy in docs/app/types/run.ts (separate package, no shared build).

export const COST_PERIOD = 'monthly';

// Ordered by how often this audience actually hits them, not alphabetically.
export const CURRENCIES = [
    { code: 'USD', symbol: '$' },
    { code: 'EUR', symbol: '€' },
    { code: 'GBP', symbol: '£' },
    { code: 'CAD', symbol: 'CA$' },
    { code: 'AUD', symbol: 'A$' },
    { code: 'CHF', symbol: 'CHF' },
    { code: 'SEK', symbol: 'kr' },
    { code: 'NOK', symbol: 'kr' },
    { code: 'DKK', symbol: 'kr' },
    { code: 'PLN', symbol: 'zł' },
    { code: 'INR', symbol: '₹' },
    { code: 'SGD', symbol: 'S$' },
    { code: 'JPY', symbol: '¥' },
    { code: 'BRL', symbol: 'R$' },
    { code: 'NZD', symbol: 'NZ$' },
    { code: 'ZAR', symbol: 'R' },
    { code: 'MXN', symbol: 'MX$' },
];

export const CURRENCY_CODES = CURRENCIES.map((currency) => currency.code);

export const DEFAULT_CURRENCY = 'USD';

export const currencySymbol = (code) => CURRENCIES.find((currency) => currency.code === code)?.symbol ?? '';

// Symbols that map to exactly one currency. `kr` is deliberately absent —
// it's SEK, NOK, and DKK, and guessing wrong is worse than not guessing.
const UNAMBIGUOUS_SYMBOLS = [
    ['R$', 'BRL'],
    ['€', 'EUR'],
    ['£', 'GBP'],
    ['₹', 'INR'],
    ['¥', 'JPY'],
    ['$', 'USD'],
];

export const parseCostAmount = (value) => {
    if( typeof value === 'number' ) {
        return Number.isFinite(value) ? value : null;
    }

    if( typeof value !== 'string' ) {
        return null;
    }

    const match = value.replace(/,/g, '').match(/\d+(?:\.\d+)?/);

    if( !match ) {
        return null;
    }

    const amount = Number.parseFloat(match[0]);

    return Number.isFinite(amount) ? amount : null;
};

// Best-effort read of a currency out of free text. An explicit ISO code wins
// over a symbol, because "20 EUR" is unambiguous and "$" never is.
const parseCostCurrency = (value) => {
    const code = String(value).toUpperCase().match(/\b([A-Z]{3})\b/)?.[1];

    if( code && CURRENCY_CODES.includes(code) ) {
        return code;
    }

    return UNAMBIGUOUS_SYMBOLS.find(([symbol]) => value.includes(symbol))?.[1] ?? null;
};

/**
 * Coerce anything we might read — the structured shape, a bare number, or a
 * free-text cost from a run saved before this was structured — into the
 * canonical shape, or null when there's no usable number in it.
 */
export const normalizeCost = (value) => {
    if( value == null || value === '' ) {
        return null;
    }

    if( typeof value === 'object' && !Array.isArray(value) ) {
        const amount = parseCostAmount(value.amount);

        if( amount === null ) {
            return null;
        }

        const currency = String(value.currency ?? '').toUpperCase();

        return {
            amount,
            currency: CURRENCY_CODES.includes(currency) ? currency : DEFAULT_CURRENCY,
            period: COST_PERIOD,
        };
    }

    const amount = parseCostAmount(value);

    if( amount === null ) {
        return null;
    }

    return {
        amount,
        // A legacy string with no currency marker was written when the field
        // was labelled with a "$24/mo" placeholder, so USD is the honest read.
        currency: (typeof value === 'string' ? parseCostCurrency(value) : null) ?? DEFAULT_CURRENCY,
        period: COST_PERIOD,
    };
};

const formatAmount = (amount, currency) => {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        // Unknown code — show the number and the code rather than nothing.
        return `${amount} ${currency}`;
    }
};

/** "€20/mo" — the currency the submitter actually pays, never converted. */
export const formatCost = (value) => {
    const cost = normalizeCost(value);

    return cost ? `${formatAmount(cost.amount, cost.currency)}/mo` : null;
};

/** Build the stored shape from the two form inputs, or null if left empty. */
export const buildCost = (amount, currency) => {
    const parsed = parseCostAmount(amount);

    if( parsed === null ) {
        return null;
    }

    return {
        amount: parsed,
        currency: CURRENCY_CODES.includes(currency) ? currency : DEFAULT_CURRENCY,
        period: COST_PERIOD,
    };
};
