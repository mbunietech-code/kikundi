<?php

namespace App\Support;

/**
 * Supported languages. Text is written in Swahili in the code and
 * translated to English through lang/en.json.
 */
class Locale
{
    public const SUPPORTED = ['en' => 'English', 'sw' => 'Kiswahili'];

    public static function valid(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    /** The group's default, used for new visitors and members with no choice. */
    public static function default(): string
    {
        $locale = Settings::get('default_locale') ?: config('app.locale');

        return self::valid($locale) ? $locale : 'en';
    }
}
