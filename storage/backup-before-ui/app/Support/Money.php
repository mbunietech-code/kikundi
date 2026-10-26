<?php

namespace App\Support;

class Money
{
    /** Amounts are stored as whole shillings (TZS has no minor unit in use). */
    public static function format(int $amount, string $currency = 'TZS'): string
    {
        $prefix = $currency === 'TZS' ? 'TSh' : $currency;

        return $prefix.' '.number_format($amount);
    }
}
