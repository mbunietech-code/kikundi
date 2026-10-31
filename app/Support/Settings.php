<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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
        // Days after a fixed contribution's last day before an unpaid member is made not active; 0 = never.
        'event_grace_days' => '30',
        // Payment deadlines and SMS reminders.
        'dues_due_day' => '10',
        'reminder_days_before' => '3',
        'reminders_enabled' => '1',
        'backup_keep_days' => '14',
        // Empty means the APP_LOCALE from .env.
        'default_locale' => '',
        // Beem SMS. Empty values fall back to .env.
        'sms_driver' => '',
        'beem_api_key' => '',
        'beem_secret_key' => '',
        'beem_sender_id' => '',
        // Mobile-money collection: "manual", "azampay" or "clickpesa"; empty = .env.
        'payment_gateway' => '',
        'clickpesa_client_id' => '',
        'clickpesa_api_key' => '',
        'clickpesa_checksum_key' => '',
        'clickpesa_webhook_token' => '',
    ];

    /** Stored encrypted and never shown back on a page. */
    public const SECRETS = ['beem_api_key', 'beem_secret_key', 'clickpesa_api_key', 'clickpesa_checksum_key'];

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        $values = array_merge(self::DEFAULTS, array_filter($stored, fn ($value) => $value !== null && $value !== ''));

        foreach (self::SECRETS as $key) {
            if ($values[$key] !== '') {
                try {
                    $values[$key] = Crypt::decryptString($values[$key]);
                } catch (DecryptException) {
                    // A changed APP_KEY makes old secrets unreadable; treat them as not set.
                    $values[$key] = '';
                }
            }
        }

        return $values;
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

            if (in_array($key, self::SECRETS, true) && filled($value)) {
                $value = Crypt::encryptString($value);
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
