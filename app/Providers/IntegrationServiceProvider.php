<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Integrations\AzamPay\AzamPayGateway;
use App\Integrations\Beem\BeemSmsGateway;
use App\Integrations\Beem\LogSmsGateway;
use App\Integrations\Beem\ManualPaymentGateway;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => config('beem.sms.driver') === 'beem'
            ? new BeemSmsGateway
            : new LogSmsGateway);

        // "manual" until the group's AzamPay account is live; then "azampay".
        $this->app->bind(PaymentGateway::class, fn () => config('services.payments.driver') === 'azampay'
            ? new AzamPayGateway
            : new ManualPaymentGateway);
    }
}
