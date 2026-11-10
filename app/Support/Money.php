<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money arithmetic in whole kobo, with no floating point anywhere
 * (specification section 1). Percentages are written as text with up to two
 * decimals ("24.75") and worked in hundredths of a percent.
 */
class Money
{
    /** "1,500.50" or "1500.5" naira → 150050 kobo. Refuses anything else. */
    public static function parseNaira(string $text): int
    {
        $text = str_replace([',', ' ', '₦'], '', trim($text));

        if (preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            throw new InvalidArgumentException('Enter an amount in naira, with up to two decimals, e.g. 1500.50');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** "24.75" → 2475 hundredths of a percent. */
    public static function basisPoints(string $percent): int
    {
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim($percent), $m) !== 1) {
            throw new InvalidArgumentException("Not a percentage: [{$percent}]");
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** A share of an amount, rounded down to the kobo. */
    public static function share(int $kobo, string $percent): int
    {
        return intdiv($kobo * self::basisPoints($percent), 10_000);
    }

    /**
     * Splits an amount by percentages that add up to 100, so the parts add up
     * to the amount exactly. Each part is rounded down; what rounding leaves
     * over goes to $remainderTo (section 7: to the compensation budget).
     *
     * @param  array<string, string>  $percents  name => percentage
     * @return array<string, int> name => kobo
     */
    public static function allocate(int $kobo, array $percents, string $remainderTo): array
    {
        if (! array_key_exists($remainderTo, $percents)) {
            throw new InvalidArgumentException("[{$remainderTo}] is not one of the parts.");
        }
        if (array_sum(array_map(self::basisPoints(...), $percents)) !== 10_000) {
            throw new InvalidArgumentException('The percentages must add up to exactly 100.');
        }

        $parts = array_map(fn (string $p) => self::share($kobo, $p), $percents);
        $parts[$remainderTo] += $kobo - array_sum($parts);

        return $parts;
    }
}
