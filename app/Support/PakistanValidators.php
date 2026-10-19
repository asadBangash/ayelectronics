<?php

namespace App\Support;

class PakistanValidators
{
    public static function normalizeCnic(?string $cnic): ?string
    {
        if ($cnic === null || trim($cnic) === '') {
            return null;
        }

        return preg_replace('/\D/', '', $cnic);
    }

    public static function isValidCnic(?string $cnic): bool
    {
        $digits = self::normalizeCnic($cnic);

        return $digits !== null && strlen($digits) === 13;
    }

    public static function formatCnic(?string $cnic): ?string
    {
        $digits = self::normalizeCnic($cnic);
        if ($digits === null) {
            return null;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '92') && strlen($digits) >= 12) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+92'.substr($digits, 1);
        }

        return $phone;
    }

    public static function isValidMobile(?string $phone): bool
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '92')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 && $digits[0] === '3';
    }
}
