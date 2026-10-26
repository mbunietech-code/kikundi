<?php

namespace App\Contracts;

use App\Models\PaymentIntent;

interface PaymentGateway
{
    /** Ask the provider to collect the intent's amount. Never marks it paid. */
    public function requestCollection(PaymentIntent $intent): void;

    /** Whether the member will get a payment prompt on their phone for this intent. */
    public function promptsOnPhone(PaymentIntent $intent): bool;
}
