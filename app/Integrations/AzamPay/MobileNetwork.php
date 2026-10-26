<?php

namespace App\Integrations\AzamPay;

use App\Support\Phone;

/**
 * Tanzanian mobile-money networks by number prefix, with AzamPay's provider
 * name and the payment method key used in this system.
 */
class MobileNetwork
{
    /** prefix after 255 => [AzamPay provider, payment method] */
    private const PREFIXES = [
        '74' => ['Mpesa', 'mpesa'], '75' => ['Mpesa', 'mpesa'], '76' => ['Mpesa', 'mpesa'],
        '68' => ['Airtel', 'airtelmoney'], '69' => ['Airtel', 'airtelmoney'], '78' => ['Airtel', 'airtelmoney'],
        '65' => ['Tigo', 'tigopesa'], '67' => ['Tigo', 'tigopesa'], '71' => ['Tigo', 'tigopesa'], '77' => ['Tigo', 'tigopesa'],
        '61' => ['Halopesa', 'halopesa'], '62' => ['Halopesa', 'halopesa'],
    ];

    /** AzamPay operator name in callbacks => payment method */
    private const OPERATORS = ['mpesa' => 'mpesa', 'airtel' => 'airtelmoney', 'tigo' => 'tigopesa', 'mixx' => 'tigopesa', 'halopesa' => 'halopesa', 'azampesa' => 'azampesa'];

    public static function provider(?string $msisdn): ?string
    {
        return self::lookup($msisdn)[0] ?? null;
    }

    public static function method(?string $msisdn): ?string
    {
        return self::lookup($msisdn)[1] ?? null;
    }

    public static function methodForOperator(?string $operator): string
    {
        return self::OPERATORS[strtolower(preg_replace('/[^a-z]/i', '', (string) $operator))] ?? 'azampay';
    }

    private static function lookup(?string $msisdn): ?array
    {
        $number = Phone::normalise((string) $msisdn);

        return strlen($number) === 12 && str_starts_with($number, '255') ? (self::PREFIXES[substr($number, 3, 2)] ?? null) : null;
    }
}
