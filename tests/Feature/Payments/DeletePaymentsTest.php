<?php

namespace Tests\Feature\Payments;

use App\Models\Allocation;
use App\Models\DeletionLog;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Balances\BalanceService;
use App\Services\Payments\PaymentIntentService;
use App\Services\Payments\VerifiedPaymentService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeletePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed([DatabaseSeeder::class, DemoActivitySeeder::class]);
        Queue::fake();
    }

    private function officer(string $role = 'system_admin'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    private function member(string $number): Member
    {
        return Member::query()->where('member_number', $number)->firstOrFail();
    }

    public function test_admin_deletes_a_payment_and_it_is_logged(): void
    {
        $neema = $this->member('M003');
        $savings = app(BalanceService::class)->memberSavings($neema);
        $intent = PaymentIntent::query()->where('credited_member_id', $neema->id)->where('target_type', 'savings')->where('status', 'succeeded')->firstOrFail();
        $paymentId = Payment::query()->where('payment_intent_id', $intent->id)->value('id');
        $admin = $this->officer();

        $this->actingAs($admin)->get('/payments?status=succeeded')->assertSee('action="'.route('payments.destroy', $intent).'"', false);
        $this->actingAs($admin)->delete("/payments/{$intent->id}", ['reason' => 'Imewekwa mara mbili'])->assertRedirect()->assertSessionHas('status');

        $this->assertModelMissing($intent);
        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
        $this->assertDatabaseMissing('allocations', ['payment_id' => $paymentId]);
        $this->assertSame($savings - (int) $intent->amount, app(BalanceService::class)->memberSavings($neema));

        $log = DeletionLog::query()->sole();
        $this->assertSame('payment', $log->kind);
        $this->assertSame((int) $intent->amount, $log->amount);
        $this->assertSame('Imewekwa mara mbili', $log->reason);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($intent->reference, $log->details['payments'][0]['reference']);

        $this->actingAs($admin)->get('/admin/deletions')->assertOk()->assertSee('Imewekwa mara mbili')->assertSee($intent->reference);
    }

    public function test_a_reason_is_required(): void
    {
        $intent = PaymentIntent::query()->where('status', 'succeeded')->firstOrFail();

        $this->actingAs($this->officer())->delete("/payments/{$intent->id}", ['reason' => ''])->assertSessionHasErrors('reason');

        $this->assertModelExists($intent);
    }

    public function test_admin_deletes_a_member_with_all_their_payments_after_typing_the_number(): void
    {
        $neema = $this->member('M003');
        $paid = (int) PaymentIntent::query()->where('credited_member_id', $neema->id)->where('status', 'succeeded')->sum('amount');
        $admin = $this->officer();

        $this->actingAs($admin)->get("/members/{$neema->id}/delete")->assertOk()->assertSee('TSh '.number_format($paid));

        $this->actingAs($admin)->delete("/members/{$neema->id}/with-payments", ['reason' => 'Aliwekwa kwa majaribio', 'confirm_number' => 'M001'])
            ->assertSessionHasErrors('confirm_number');
        $this->assertModelExists($neema);

        $this->actingAs($admin)->delete("/members/{$neema->id}/with-payments", ['reason' => 'Aliwekwa kwa majaribio', 'confirm_number' => 'm003'])
            ->assertRedirect('/members');

        $this->assertModelMissing($neema);
        $this->assertFalse(PaymentIntent::query()->where('credited_member_id', $neema->id)->exists());
        $this->assertFalse(Allocation::query()->where('credited_member_id', $neema->id)->exists());

        $log = DeletionLog::query()->sole();
        $this->assertSame('member', $log->kind);
        $this->assertSame($paid, $log->amount);
        $this->assertStringContainsString('M003', $log->subject);
    }

    public function test_payments_made_for_a_friend_are_shown_before_deleting(): void
    {
        $amina = $this->member('M001');
        $baraka = $this->member('M002');
        $intent = app(PaymentIntentService::class)->create($amina, $baraka, 'savings', null, 3000, 'friend:test', 'office', $amina->phone);
        app(VerifiedPaymentService::class)->post($intent, 'cash', 'R-FRIEND', 3000, 'manual');

        $this->actingAs($this->officer())->get("/members/{$amina->id}/delete")->assertOk()
            ->assertSee('Malipo aliyolipia wanachama wengine')->assertSee('Baraka Mussa')->assertSee($intent->reference);
    }

    public function test_only_the_system_admin_can_delete_payments_or_see_the_log(): void
    {
        $intent = PaymentIntent::query()->where('status', 'succeeded')->firstOrFail();
        $neema = $this->member('M003');

        foreach (['treasurer', 'secretary', 'chair'] as $role) {
            $user = $this->officer($role);
            $this->actingAs($user)->delete("/payments/{$intent->id}", ['reason' => 'Jaribio la ruhusa'])->assertForbidden();
            $this->actingAs($user)->delete("/members/{$neema->id}/with-payments", ['reason' => 'Jaribio la ruhusa', 'confirm_number' => 'M003'])->assertForbidden();
            $this->actingAs($user)->get('/admin/deletions')->assertForbidden();
        }

        $this->actingAs($this->officer('treasurer'))->get('/payments?status=succeeded')
            ->assertDontSee('action="'.route('payments.destroy', $intent).'"', false);
        $this->assertModelExists($intent);
    }
}
