<?php

namespace App\Contracts;

interface SmsGateway
{
    /** Returns the provider's message id when it gives one. */
    public function send(string $msisdn, string $message): ?string;
}
