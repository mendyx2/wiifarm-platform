<?php

namespace App\Services\Usdt;

/**
 * Tron addresses in their two spellings: the "T..." one people copy
 * (Base58Check) and the hex one inside transactions ("41" and 20 bytes).
 * The 4-byte checksum means a mistyped address is refused, not accepted as
 * someone else's. Plain PHP, no maths extension needed.
 */
class TronAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function isValid(?string $address): bool
    {
        return $address !== null && self::toHex($address) !== null;
    }

    /** "T..." to "41" and 40 hex characters, or null if it is not a valid Tron address. */
    public static function toHex(string $address): ?string
    {
        if (! preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
            return null;
        }
        $bytes = self::decode58($address);
        if ($bytes === null || strlen($bytes) !== 25 || $bytes[0] !== "\x41") {
            return null;
        }
        [$payload, $check] = [substr($bytes, 0, 21), substr($bytes, 21)];

        return hash_equals(self::checksum($payload), $check) ? bin2hex($payload) : null;
    }

    /** Hex to "T...": "41" and 40 hex, 40 hex, or a 64-hex event topic (its last 40). Null if malformed. */
    public static function fromHex(string $hex): ?string
    {
        $hex = strtolower(preg_replace('/^0x/i', '', trim($hex)));
        if (strlen($hex) === 64) {
            $hex = substr($hex, 24);
        }
        if (strlen($hex) === 40) {
            $hex = '41'.$hex;
        }
        if (! preg_match('/^41[0-9a-f]{40}$/', $hex)) {
            return null;
        }
        $payload = hex2bin($hex);

        return self::encode58($payload.self::checksum($payload));
    }

    private static function checksum(string $payload): string
    {
        return substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
    }

    private static function encode58(string $bytes): string
    {
        $digits = [0];   // base 58, least significant first
        foreach (str_split($bytes) as $char) {
            $carry = ord($char);
            foreach ($digits as $i => $digit) {
                $carry += $digit * 256;
                $digits[$i] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }
        $out = '';
        for ($i = 0; $i < strlen($bytes) && $bytes[$i] === "\0"; $i++) {
            $out .= '1';
        }
        foreach (array_reverse($digits) as $i => $digit) {
            if ($i === 0 && $digit === 0 && count($digits) > 1) {
                continue;
            }
            $out .= self::ALPHABET[$digit];
        }

        return $out;
    }

    private static function decode58(string $text): ?string
    {
        $bytes = [0];   // base 256, least significant first
        foreach (str_split($text) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return null;
            }
            $carry = $value;
            foreach ($bytes as $i => $byte) {
                $carry += $byte * 58;
                $bytes[$i] = $carry & 0xFF;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xFF;
                $carry >>= 8;
            }
        }
        $out = '';
        for ($i = 0; $i < strlen($text) && $text[$i] === '1'; $i++) {
            $out .= "\0";
        }
        $body = implode('', array_map('chr', array_reverse($bytes)));

        return $out.ltrim($body, "\0");
    }
}
