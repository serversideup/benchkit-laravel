<?php

namespace App\Http\Requests\Runs;

use App\Support\HostCost;
use Illuminate\Validation\Rule;

/**
 * Cost arrives as { amount, currency, period } from every entry point — a new
 * run, a saved snapshot, an edited one — so the rules live in one place.
 * Nothing here accepts free text: "$24/mo" and "20 EUR" can't be compared,
 * and a string field is how you end up with both.
 */
trait ValidatesHostCost
{
    /**
     * @return array<string, array<mixed>>
     */
    protected static function costRules(string $prefix = 'cost'): array
    {
        return [
            "{$prefix}.amount" => ["required_with:{$prefix}", 'numeric', 'min:0', 'max:1000000'],
            "{$prefix}.currency" => ["required_with:{$prefix}", 'string', Rule::in(HostCost::CURRENCIES)],
            // Everything is monthly. One unit means no conversion and no
            // ambiguity about what the number means.
            "{$prefix}.period" => ['nullable', 'string', Rule::in([HostCost::PERIOD])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function costMessages(string $prefix = 'cost'): array
    {
        return [
            "{$prefix}.amount.numeric" => 'The monthly cost must be a number.',
            "{$prefix}.amount.required_with" => 'A monthly cost needs an amount.',
            "{$prefix}.currency.in" => 'That currency is not one BenchKit records.',
            "{$prefix}.currency.required_with" => 'A monthly cost needs a currency.',
            "{$prefix}.period.in" => 'Costs are always recorded per month.',
        ];
    }
}
