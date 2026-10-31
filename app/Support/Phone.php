<?php

namespace App\Support;

class Phone
{
    /**
     * A Tanzanian mobile number (2557XXXXXXXX / 2556XXXXXXXX), or any other
     * country's number in international form, 8 to 15 digits without the +.
     */
    public const RULE = '/^(255[67]\d{8}|(?!255)[1-9]\d{7,14})$/';

    /**
     * Normalise to international digits, the format Beem sends as msisdn.
     * Local Tanzanian forms (07..., 7...) get 255; a foreign number must be
     * typed with + or 00 and its country code, e.g. +86 138 0013 8000.
     */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return $digits;
    }

    /** How a stored number is shown and typed back: 0712... at home, +86... abroad. */
    public static function display(?string $phone): string
    {
        if (blank($phone)) {
            return '';
        }

        return str_starts_with($phone, '255') && strlen($phone) === 12 ? '0'.substr($phone, 3) : '+'.$phone;
    }
}
