<?php

namespace App\Support;

/**
 * Bangladeshi mobile numbers are stored as 11 digits: 01XXXXXXXXX.
 * Staff type them in many ways ("+880 1711-000000", "8801711000000", "01711 000000").
 */
final class Phone
{
    /** Regex for a normalised BD mobile number (operators 013–019). */
    public const BD_MOBILE = '/^01[3-9]\d{8}$/';

    /** Strip spaces/dashes and the +88 / 88 country code. Returns null for empty input. */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
