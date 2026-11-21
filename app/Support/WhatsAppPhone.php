<?php

namespace App\Support;

final class WhatsAppPhone
{
    public static function normalize(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if (! preg_match('/^\+?[0-9\s().-]+$/D', $phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $hasPlus = str_starts_with($phone, '+');
        $hasInternationalPrefix = ! $hasPlus && str_starts_with($digits, '00');

        if ($hasInternationalPrefix) {
            $digits = substr($digits, 2);
        }

        if (! $hasPlus && ! $hasInternationalPrefix) {
            if (preg_match('/^0[67][0-9]{8}$/D', $digits)) {
                return '255'.substr($digits, 1);
            }

            if (preg_match('/^[67][0-9]{8}$/D', $digits)) {
                return '255'.$digits;
            }

            if (strlen($digits) < 10) {
                return null;
            }
        }

        if (str_starts_with($digits, '255')) {
            return preg_match('/^255[67][0-9]{8}$/D', $digits) ? $digits : null;
        }

        // Keep the prefix to distinguish short international numbers from Tanzanian local numbers.
        return preg_match('/^[1-9][0-9]{7,14}$/D', $digits) ? '+'.$digits : null;
    }
}
