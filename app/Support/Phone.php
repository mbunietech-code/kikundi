<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise a Tanzanian number to 2557XXXXXXXX form, the format Beem
     * sends as msisdn.
     */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return $digits;
    }
}
