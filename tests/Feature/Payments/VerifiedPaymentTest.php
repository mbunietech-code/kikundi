<?php

namespace Tests\Feature\Payments;

use App\Models\Member;
use App\Models\Obligation;
use App\Models\Payment;
use App\Services\Balances\BalanceService;
use App\Services\Payments\PaymentIntentService;
use App\Services\Payments\VerifiedPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_receipt_posts_once_and_reduces_dues(): void
    {
        $this->seed(DatabaseSeeder::class);
        $amina = Member::query()->where('member_number', 'M001')->first();
        $obligation = Obligation::query()->where('member_id', $amina->id)->orderBy('period')->first();

        $intent = app(PaymentIntentService::class)->create($amina, $amina, 'dues', $obligation->id, 6000, 'test-1');
        $service = app(VerifiedPaymentService::class);

        $service->post($intent, 'manual', 'TX1', 6000, 'manual');
        $service->post($intent, 'manual', 'TX1', 6000, 'manual');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(4000, app(BalanceService::class)->outstandingFor($obligation));
        $this->assertSame('succeeded', $intent->fresh()->status);
    }

    public function test_amount_mismatch_is_not_posted(): void
    {
        $this->seed(DatabaseSeeder::class);
        $amina = Member::query()->where('member_number', 'M001')->first();
        $intent = app(PaymentIntentService::class)->create($amina, $amina, 'savings', null, 5000, 'test-2');

        $this->expectException(\RuntimeException::class);

        try {
            app(VerifiedPaymentService::class)->post($intent, 'manual', 'TX2', 4000, 'manual');
        } finally {
            $this->assertSame(0, Payment::query()->count());
        }
    }
}
