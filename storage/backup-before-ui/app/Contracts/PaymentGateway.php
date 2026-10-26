<?php

namespace App\Contracts;

use App\Models\PaymentIntent;

interface PaymentGateway
{
    /** Ask the provider to collect the intent's amount. Never marks it paid. */
    public function requestCollection(PaymentIntent $intent): void;
}
