<?php

namespace App\Services\Shop;

/**
 * Pickup codes (specification section 8): twelve characters in groups of four,
 * such as "K7QM-3XPA-R9TD", from letters and digits that cannot be mistaken for
 * each other (no 0/O, 1/I/L). About 10^18 possible codes, so they cannot be
 * guessed.
 *
 * The database keeps a keyed fingerprint to find an order by its code, and the
 * code itself encrypted, so only the buyer is ever shown it.
 */
class PickupCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $chars = '';
        for ($i = 0; $i < 12; $i++) {
            $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return implode('-', str_split($chars, 4));
    }

    /** "k7qm 3xpa-r9td" → "K7QM3XPAR9TD": what a stockist types, tidied. */
    public static function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /** The keyed fingerprint stored to find an order by its code. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }
}
