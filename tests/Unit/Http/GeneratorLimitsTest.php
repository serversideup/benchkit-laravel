<?php

namespace Tests\Unit\Http;

use App\Support\Http\GeneratorLimits;
use Tests\TestCase;

/**
 * A self-test's ceiling is measured on the machine, so the measurement has to
 * come back as numbers the profile can use, or as nothing, never as text.
 */
class GeneratorLimitsTest extends TestCase
{
    public function test_the_limits_are_integers_or_unknown(): void
    {
        $limits = GeneratorLimits::measure();

        $this->assertSame(['fd_limit', 'port_range'], array_keys($limits));

        foreach ($limits as $name => $value) {
            $this->assertTrue($value === null || (is_int($value) && $value > 0), "{$name} should be a positive integer or null.");
        }
    }

    public function test_the_descriptor_limit_is_raised_before_it_is_read(): void
    {
        $soft = (int) trim((string) shell_exec('ulimit -n'));
        $hard = trim((string) shell_exec('ulimit -Hn'));

        if ($hard === 'unlimited' || (int) $hard <= $soft) {
            $this->markTestSkipped('This machine has no headroom between its soft and hard limits.');
        }

        $this->assertGreaterThan($soft, GeneratorLimits::measure()['fd_limit']);
    }
}
