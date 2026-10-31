<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable system settings, such as the system name shown on the
 * web portal, in USSD menus and in SMS messages.
 */
class Settings
{
    public const CACHE_KEY = 'mjeshi.settings';

    /** Keys an administrator may edit, with their defaults. */
    public const DEFAULTS = [
        'system_name' => 'Mfumo wa Kikundi',
        'support_contact' => '',
        'ussd_min_amount' => '500',
        'ussd_max_amount' => '5000000',
        // Membership rules from the group constitution.
        'grace_months' => '3',
        'entry_fee' => '20000',
        'reinstatement_fee' => '20000',
        'reinstate_requires_arrears' => '1',
        'min_guarantors' => '0',
        // Payment deadlines and SMS reminders.
        'dues_due_day' => '10',
        'reminder_days_before' => '3',
        'reminders_enabled' => '1',
        'backup_keep_days' => '14',
        // Empty means the APP_LOCALE from .env.
        'default_locale' => '',
    ];

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($value) => $value !== null && $value !== ''));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** The default name is translated; a name the group chose is shown as written. */
    public static function systemName(): string
    {
        return __(self::get('system_name'));
    }
}
