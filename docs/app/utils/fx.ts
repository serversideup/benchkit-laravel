// Approximate USD conversion, used for one thing only: sorting the gallery by
// price/performance across runs billed in different currencies.
//
// Deliberately a checked-in snapshot rather than a live feed, and deliberately
// nowhere near stored data. A submitted run always keeps the currency the
// submitter is actually billed in — converting on the way in would make the
// file wrong the moment rates moved, and there'd be no way to tell later which
// rate had been used. Converting at render keeps the data honest and makes a
// stale table a display nit rather than a corruption.
//
// Precision doesn't matter here. These feed a sort and a rough "$/1k req/s"
// figure that the UI labels approximate; nobody is doing accounting with them.
// Refresh occasionally when they've visibly drifted.

export const FX_RATES_AS_OF = 'August 2026'

const USD_PER_UNIT: Record<string, number> = {
    USD: 1,
    EUR: 1.08,
    GBP: 1.27,
    CAD: 0.73,
    AUD: 0.66,
    CHF: 1.12,
    SEK: 0.095,
    NOK: 0.093,
    DKK: 0.145,
    PLN: 0.25,
    INR: 0.012,
    SGD: 0.74,
    JPY: 0.0067,
    BRL: 0.18,
    NZD: 0.60,
    ZAR: 0.055,
    MXN: 0.055
}

/**
 * Roughly what a monthly cost works out to in USD, or null when there's no
 * cost or the currency isn't in the table (in which case the run simply drops
 * out of value-based sorting rather than being ranked on a guess).
 */
export function approximateMonthlyUsd(amount: number | null | undefined, currency: string | null | undefined): number | null {
    if (amount == null || currency == null) return null

    const rate = USD_PER_UNIT[currency]

    return rate == null ? null : amount * rate
}
