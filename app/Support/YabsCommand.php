<?php

namespace App\Support;

use App\Actions\Results\YabsResults;

/**
 * Builds the yabs command line. Disabled tests are opted out with their
 * skip flags; results are always written as JSON (-w) to the results path.
 */
class YabsCommand
{
    /**
     * @param  array{disk?: mixed, geekbench?: mixed, geekbench_version?: mixed, iperf?: mixed}  $options
     */
    public function build(array $options): string
    {
        $flags = '';

        if (! filter_var($options['disk'] ?? false, FILTER_VALIDATE_BOOL)) {
            $flags .= ' -f';
        }

        if (! filter_var($options['geekbench'] ?? false, FILTER_VALIDATE_BOOL)) {
            $flags .= ' -g';
        } else {
            $flags .= match ((int) ($options['geekbench_version'] ?? 6)) {
                4 => ' -4',
                5 => ' -5',
                default => ' -6',
            };
        }

        if (! filter_var($options['iperf'] ?? false, FILTER_VALIDATE_BOOL)) {
            $flags .= ' -i';
        }

        return sprintf('%s%s -w %s', base_path('vendor/bin/yabs'), $flags, (new YabsResults)->path());
    }
}
