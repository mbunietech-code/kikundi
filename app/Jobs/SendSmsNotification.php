<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $msisdn, public string $message) {}

    public function handle(SmsGateway $sms): void
    {
        $sms->send($this->msisdn, $this->message);
    }
}
