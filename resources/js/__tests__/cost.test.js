import { describe, expect, it } from 'vitest';
import { normalizeCost, parseCostAmount } from '@/cost';

// Mirrors tests/Unit/HostCostTest.php: the two parsers must agree, because
// the browser decides what the form sends and the server decides what is kept.
describe('normalizeCost', () => {
    it.each([
        ['$24/mo', 24, 'USD'],
        ['24', 24, 'USD'],
        ['20 EUR', 20, 'EUR'],
        ['£15/month', 15, 'GBP'],
        ['$1,200/mo', 1200, 'USD'],
        ['$30 CAD', 30, 'CAD'],
    ])('reads legacy free text %s', (value, amount, currency) => {
        expect(normalizeCost(value)).toEqual({ amount, currency, period: 'monthly' });
    });

    it.each(['$0.05/hr', '0.05 per hour', '5 cents hourly', '$240/yr', '240 USD annually', '€1/day'])(
        'refuses a price for another period: %s',
        (value) => {
            expect(normalizeCost(value)).toBeNull();
        },
    );

    it('still reads monthly wording', () => {
        expect(normalizeCost('24 per month').amount).toBe(24);
        expect(normalizeCost('24 monthly').amount).toBe(24);
    });

    it('drops anything without a usable amount', () => {
        expect(normalizeCost(null)).toBeNull();
        expect(normalizeCost('')).toBeNull();
        expect(normalizeCost('free')).toBeNull();
        expect(normalizeCost({ currency: 'USD' })).toBeNull();
    });

    it('falls back to USD for an unknown currency', () => {
        expect(normalizeCost({ amount: 24, currency: 'DOGE' }).currency).toBe('USD');
    });
});

describe('parseCostAmount', () => {
    it('rejects negatives and rounds to cents, as the server does', () => {
        expect(parseCostAmount(-5)).toBeNull();
        expect(parseCostAmount(24.999)).toBe(25);
        expect(parseCostAmount('24.999')).toBe(25);
    });
});
