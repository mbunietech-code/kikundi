<?php

namespace Tests\Feature\Payments;

use App\Contracts\PaymentGateway;
use App\Integrations\AzamPay\AzamPayClient;
use App\Integrations\AzamPay\AzamPayGateway;
use App\Integrations\AzamPay\MobileNetwork;
use App\Jobs\RequestMobileMoneyCollection;
use App\Jobs\SendSmsNotification;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentIntentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AzamPayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'beem.ussd_group_id' => 1,
            'beem.ussd_token' => 't',
            'services.payments.driver' => 'azampay',
            'azampay.environment' => 'sandbox',
            'azampay.app_name' => 'mjeshi',
            'azampay.client_id' => 'client',
            'azampay.client_secret' => 'secret',
            'azampay.api_key' => 'api-key',
            'azampay.callback_token' => 'cb-token',
            'azampay.callback_password' => 'cb-pass',
        ]);
        $this->seed(DatabaseSeeder::class);

        // An M-Pesa number, so pushes are possible.
        Member::query()->where('member_number', 'M001')->update(['phone' => '255754000001']);
    }

    private function fakeAzamPay(array $checkout = ['success' => true, 'transactionId' => 'AZ-1', 'message' => 'Request in progress']): void
    {
        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 'tok-123', 'expire' => now()->addHour()->toIso8601String()], 'success' => true]),
            'sandbox.azampay.co.tz/*' => Http::response($checkout),
        ]);
    }

    private function intent(string $channel = 'ussd', ?string $msisdn = '255754000001'): PaymentIntent
    {
        $amina = Member::query()->where('member_number', 'M001')->first();

        return app(PaymentIntentService::class)->create($amina, $amina, 'savings', null, 5000, 'test:'.uniqid(), $channel, $msisdn);
    }

    private function sendCallback(array $body, string $token = 'cb-token')
    {
        return $this->postJson("/integrations/azampay/callback/{$token}", $body + ['password' => 'cb-pass']);
    }

    public function test_networks_are_recognised_from_the_number(): void
    {
        $this->assertSame('Mpesa', MobileNetwork::provider('0754 000 001'));
        $this->assertSame('Airtel', MobileNetwork::provider('255688000000'));
        $this->assertSame('Tigo', MobileNetwork::provider('0712000000'));
        $this->assertSame('Halopesa', MobileNetwork::provider('0621000000'));
        $this->assertNull(MobileNetwork::provider('255700000001'));
        $this->assertSame('airtelmoney', MobileNetwork::methodForOperator('Airtel'));
        $this->assertSame('mpesa', MobileNetwork::methodForOperator('M-Pesa'));
    }

    public function test_ussd_request_queues_one_push_and_the_job_calls_azampay(): void
    {
        $this->assertInstanceOf(AzamPayGateway::class, app(PaymentGateway::class));
        Queue::fake();
        $this->fakeAzamPay();

        $intent = $this->intent();

        Queue::assertPushed(RequestMobileMoneyCollection::class, 1);
        (new RequestMobileMoneyCollection($intent->id))->handle(app(AzamPayClient::class));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/AppRegistration/GenerateToken')
            && $r['appName'] === 'mjeshi' && $r['clientId'] === 'client' && $r['clientSecret'] === 'secret');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox.azampay.co.tz/azampay/mno/checkout'
            && $r->hasHeader('Authorization', 'Bearer tok-123')
            && $r->hasHeader('X-API-Key', 'api-key')
            && $r['accountNumber'] === '255754000001' && $r['amount'] === '5000' && $r['currency'] === 'TZS'
            && $r['externalId'] === $intent->reference && $r['provider'] === 'Mpesa');
        $this->assertSame('pending', $intent->fresh()->status, 'Only the callback may mark it paid.');
    }

    public function test_no_push_for_office_payments_or_unknown_networks(): void
    {
        Queue::fake();

        $this->intent('office');
        $this->intent('ussd', '255700000009');

        Queue::assertNotPushed(RequestMobileMoneyCollection::class);
    }

    public function test_failed_push_leaves_the_request_for_the_treasurer(): void
    {
        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 'tok']]),
            'sandbox.azampay.co.tz/*' => Http::response(['message' => 'Bad request'], 400),
        ]);
        Queue::fake();
        $intent = $this->intent();

        (new RequestMobileMoneyCollection($intent->id))->handle(app(AzamPayClient::class));

        $this->assertSame('pending', $intent->fresh()->status);
    }

    public function test_successful_callback_posts_the_payment_once_and_sends_a_receipt(): void
    {
        Queue::fake();
        $intent = $this->intent();
        $body = ['transactionstatus' => 'success', 'utilityref' => $intent->reference, 'reference' => 'AZ-TX-9', 'amount' => '5000', 'operator' => 'Mpesa', 'msisdn' => '255754000001'];

        $this->sendCallback($body)->assertOk()->assertJson(['success' => true]);
        $this->sendCallback($body)->assertOk();

        $this->assertSame('succeeded', $intent->fresh()->status);
        $payment = Payment::query()->where('provider_transaction_id', 'AZ-TX-9')->sole();
        $this->assertSame('mpesa', $payment->provider);
        $this->assertSame('provider', $payment->verification_method);
        Queue::assertPushed(SendSmsNotification::class, fn ($job) => str_contains($job->message, $intent->reference));
    }

    public function test_callback_field_casing_from_the_sdk_docs_is_accepted(): void
    {
        Queue::fake();
        $intent = $this->intent();

        $this->sendCallback(['transactionStatus' => 'Success', 'utilityRef' => $intent->reference, 'Reference' => 'AZ-TX-10', 'Amount' => 5000, 'Operator' => 'Airtel'])->assertOk();

        $this->assertSame('airtelmoney', Payment::query()->where('provider_transaction_id', 'AZ-TX-10')->value('provider'));
    }

    public function test_unsuccessful_callback_marks_the_request_failed(): void
    {
        Queue::fake();
        $intent = $this->intent();

        $this->sendCallback(['transactionstatus' => 'failure', 'utilityref' => $intent->reference, 'reference' => 'AZ-TX-11', 'amount' => '5000'])->assertOk();

        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertFalse(Payment::query()->where('provider_transaction_id', 'AZ-TX-11')->exists());
    }

    public function test_wrong_amount_is_not_posted(): void
    {
        Queue::fake();
        $intent = $this->intent();

        $this->sendCallback(['transactionstatus' => 'success', 'utilityref' => $intent->reference, 'reference' => 'AZ-TX-12', 'amount' => '500'])
            ->assertOk()->assertJson(['success' => false]);

        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertFalse(Payment::query()->where('provider_transaction_id', 'AZ-TX-12')->exists());
    }

    public function test_callbacks_need_the_secret_url_and_password(): void
    {
        Queue::fake();
        $intent = $this->intent();
        $body = ['transactionstatus' => 'success', 'utilityref' => $intent->reference, 'reference' => 'AZ-TX-13', 'amount' => '5000'];

        $this->postJson('/integrations/azampay/callback/wrong', $body + ['password' => 'cb-pass'])->assertNotFound();
        $this->postJson('/integrations/azampay/callback/cb-token', $body + ['password' => 'nope'])->assertUnauthorized();
        $this->postJson('/integrations/azampay/callback/cb-token', $body)->assertUnauthorized();

        $this->assertSame('pending', $intent->fresh()->status);
    }

    public function test_ussd_tells_the_member_to_expect_the_prompt(): void
    {
        Queue::fake();
        $send = fn (string $command, string $input) => $this->postJson('/integrations/beem/ussd/t', [
            'command' => $command, 'msisdn' => '255754000001', 'operator' => 'vodacom', 'session_id' => 'AZ1', 'payload' => ['response' => $input],
        ])->json('payload.request');

        $send('initiate', '0');
        $send('continue', '1234');
        $send('continue', '4');
        $send('continue', '1');
        $send('continue', '5000');

        $this->assertStringContainsString('Subiri ujumbe wa malipo kwenye simu yako', $send('continue', '1'));
        Queue::assertPushed(RequestMobileMoneyCollection::class, 1);
    }
}
