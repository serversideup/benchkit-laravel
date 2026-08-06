<?php

namespace App\Support;

/**
 * Hosting cost as structured data rather than free text.
 *
 * Cost only earns its place in a run if you can compare it — "req/s per
 * dollar" is the whole point — and that needs a number, a currency, and a
 * single period. So a cost is always:
 *
 *     ['amount' => 24.0, 'currency' => 'USD', 'period' => 'monthly']
 *
 * Period is fixed at monthly: one unit means no conversion logic and no
 * ambiguity about what a number means. Currency is stored as the submitter
 * actually pays it and is never converted here — turning EUR into USD depends
 * on a rate and a date, so it belongs at display time, not in stored data.
 *
 * The JavaScript mirror of this lives in resources/js/cost.js.
 */
class HostCost
{
    public const PERIOD = 'monthly';

    public const DEFAULT_CURRENCY = 'USD';

    /** @var array<int, string> */
    public const CURRENCIES = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'SEK', 'NOK', 'DKK',
        'PLN', 'INR', 'SGD', 'JPY', 'BRL', 'NZD', 'ZAR', 'MXN',
    ];

    /**
     * Symbols that map to exactly one currency. `kr` is deliberately absent —
     * it's SEK, NOK, and DKK, and guessing wrong is worse than not guessing.
     *
     * @var array<string, string>
     */
    protected const SYMBOLS = [
        'R$' => 'BRL',
        '€' => 'EUR',
        '£' => 'GBP',
        '₹' => 'INR',
        '¥' => 'JPY',
        '$' => 'USD',
    ];

    /**
     * Coerce anything we might be handed — the structured shape, a bare
     * number, or a free-text cost from a run saved before this was
     * structured — into the canonical shape, or null when there's no usable
     * number in it.
     *
     * @return array{amount: float, currency: string, period: string}|null
     */
    public static function normalize(mixed $value): ?array
    {
        if (is_array($value)) {
            $amount = self::amount($value['amount'] ?? null);

            return $amount === null ? null : [
                'amount' => $amount,
                'currency' => self::currency($value['currency'] ?? null),
                'period' => self::PERIOD,
            ];
        }

        $amount = self::amount($value);

        if ($amount === null) {
            return null;
        }

        return [
            'amount' => $amount,
            // A legacy string with no currency marker was written when the
            // field carried a "$24/mo" placeholder, so USD is the honest read.
            'currency' => is_string($value) ? self::currencyFromText($value) : self::DEFAULT_CURRENCY,
            'period' => self::PERIOD,
        ];
    }

    protected static function amount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $amount = (float) $value;

            return is_finite($amount) && $amount >= 0 ? round($amount, 2) : null;
        }

        if (! is_string($value)) {
            return null;
        }

        if (! preg_match('/\d+(?:\.\d+)?/', str_replace(',', '', $value), $matches)) {
            return null;
        }

        return round((float) $matches[0], 2);
    }

    protected static function currency(mixed $value): string
    {
        $code = is_string($value) ? strtoupper($value) : '';

        return in_array($code, self::CURRENCIES, true) ? $code : self::DEFAULT_CURRENCY;
    }

    /**
     * Best-effort read of a currency out of free text. An explicit ISO code
     * wins over a symbol, because "20 EUR" is unambiguous and "$" never is.
     */
    protected static function currencyFromText(string $value): string
    {
        if (preg_match('/\b([A-Z]{3})\b/', strtoupper($value), $matches) && in_array($matches[1], self::CURRENCIES, true)) {
            return $matches[1];
        }

        foreach (self::SYMBOLS as $symbol => $code) {
            if (str_contains($value, $symbol)) {
                return $code;
            }
        }

        return self::DEFAULT_CURRENCY;
    }
}
