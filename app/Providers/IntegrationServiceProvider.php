<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Integrations\AzamPay\AzamPayGateway;
use App\Integrations\Beem\BeemSmsGateway;
use App\Integrations\Beem\LogSmsGateway;
use App\Integrations\Beem\ManualPaymentGateway;
use App\Integrations\ClickPesa\ClickPesaGateway;
use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The administrator's choice in the settings wins over .env.
        $this->app->bind(SmsGateway::class, fn () => (Settings::get('sms_driver') ?: config('beem.sms.driver')) === 'beem'
            ? new BeemSmsGateway
            : new LogSmsGateway);

        $this->app->bind(PaymentGateway::class, fn () => match (Settings::get('payment_gateway') ?: config('services.payments.driver')) {
            'azampay' => new AzamPayGateway,
            'clickpesa' => new ClickPesaGateway,
            default => new ManualPaymentGateway,
        });
    }
}
