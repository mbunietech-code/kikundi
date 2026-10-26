<?php

namespace App\Contracts;

interface SmsGateway
{
    public function send(string $msisdn, string $message): void;
}
