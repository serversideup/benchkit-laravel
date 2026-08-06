<?php

namespace Tests\Unit;

use App\Support\HostCost;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostCostTest extends TestCase
{
    public function test_a_structured_cost_is_kept_as_given(): void
    {
        $this->assertSame(
            ['amount' => 20.5, 'currency' => 'EUR', 'period' => 'monthly'],
            HostCost::normalize(['amount' => 20.5, 'currency' => 'EUR', 'period' => 'monthly']),
        );
    }

    public function test_an_unknown_currency_falls_back_rather_than_storing_junk(): void
    {
        $cost = HostCost::normalize(['amount' => 24, 'currency' => 'DOGE']);

        $this->assertSame('USD', $cost['currency']);
    }

    public function test_a_cost_without_a_usable_amount_is_dropped(): void
    {
        $this->assertNull(HostCost::normalize(null));
        $this->assertNull(HostCost::normalize(''));
        $this->assertNull(HostCost::normalize('free'));
        $this->assertNull(HostCost::normalize(['currency' => 'USD']));
    }

    /**
     * Runs saved before cost was structured hold whatever the user typed into
     * a text box. Reading them has to be best-effort but never wrong in the
     * one way that matters: it must not relabel euros as dollars.
     */
    #[DataProvider('legacyCosts')]
    public function test_a_legacy_free_text_cost_is_read_best_effort(string $value, float $amount, string $currency): void
    {
        $this->assertSame(
            ['amount' => $amount, 'currency' => $currency, 'period' => 'monthly'],
            HostCost::normalize($value),
        );
    }

    /**
     * @return array<string, array{string, float, string}>
     */
    public static function legacyCosts(): array
    {
        return [
            'the placeholder people copied' => ['$24/mo', 24.0, 'USD'],
            'a bare number' => ['24', 24.0, 'USD'],
            'an explicit code' => ['20 EUR', 20.0, 'EUR'],
            'a symbol' => ['£15/month', 15.0, 'GBP'],
            'thousands separators' => ['$1,200/mo', 1200.0, 'USD'],
            'a code beating an ambiguous symbol' => ['$30 CAD', 30.0, 'CAD'],
        ];
    }
}
