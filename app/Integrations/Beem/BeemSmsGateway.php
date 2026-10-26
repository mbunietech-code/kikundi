<?php

namespace App\Integrations\Beem;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;

class BeemSmsGateway implements SmsGateway
{
    public function send(string $msisdn, string $message): void
    {
        Http::withBasicAuth(config('beem.api_key'), config('beem.secret_key'))
            ->acceptJson()
            ->timeout(15)
            ->post(config('beem.sms.url'), [
                'source_addr' => config('beem.sms.sender_id'),
                'encoding' => 0,
                'message' => $message,
                'recipients' => [['recipient_id' => 1, 'dest_addr' => $msisdn]],
            ])
            ->throw();
    }
}
