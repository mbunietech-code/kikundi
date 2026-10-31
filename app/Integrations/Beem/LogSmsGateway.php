<?php

namespace App\Integrations\Beem;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $msisdn, string $message): ?string
    {
        Log::info('SMS (not sent, log driver)', ['to' => $msisdn, 'message' => $message]);

        return null;
    }
}
