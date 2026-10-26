<?php

namespace Tests\Feature\Membership;

use App\Jobs\SendSmsNotification;
use App\Models\Member;
use App\Models\MembershipFee;
use App\Models\Obligation;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    private function officer(string $role): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    private function member(string $number): Member
    {
        return Member::query()->where('member_number', $number)->firstOrFail();
    }

    /** Give a member unpaid dues for the given number of past months. */
    private function owePastMonths(Member $member, int $months): void
    {
        foreach (range(1, $months) as $back) {
            Obligation::query()->firstOrCreate(
                ['member_id' => $member->id, 'period' => now()->subMonthsNoOverflow($back)->format('Y-m')],
                ['group_id' => $member->group_id, 'amount_due' => 10000],
            );
        }
    }

    private function pay(Member $member, string $target, int $targetId, int $amount, string $receipt): void
    {
        $this->actingAs($this->officer('treasurer'))->post('/payments', [
            'credited_member_id' => $member->id, 'target_type' => $target,
            $target === 'dues' ? 'obligation_id' : 'fee_id' => $targetId,
            'amount' => $amount, 'method' => 'cash', 'transaction_id' => $receipt,
        ])->assertRedirect('/payments?status=succeeded');
    }

    public function test_new_member_waits_for_entry_fee_and_becomes_active_once_paid(): void
    {
        $this->actingAs($this->officer('secretary'))->post('/members', [
            'member_number' => '', 'full_name' => 'Asha Ali', 'phone' => '0712345678', 'joined_at' => '2026-10-01',
        ])->assertRedirect();

        $asha = Member::query()->where('full_name', 'Asha Ali')->firstOrFail();
        $this->assertSame('M007', $asha->member_number);
        $this->assertSame('pending', $asha->status);

        $fee = MembershipFee::query()->where('member_id', $asha->id)->firstOrFail();
        $this->assertSame(20000, (int) $fee->amount_due);

        $this->pay($asha, 'membership_fee', $fee->id, 20000, 'RISITI-K1');

        $this->assertSame('active', $asha->fresh()->status);
        $this->assertSame(['pending', 'active'], $asha->statusChanges()->orderBy('id')->pluck('to_status')->all());
        Queue::assertPushed(SendSmsNotification::class, fn ($job) => str_contains($job->message, 'mwanachama hai'));
    }

    public function test_existing_member_can_be_registered_without_entry_fee(): void
    {
        $this->actingAs($this->officer('secretary'))->post('/members', [
            'member_number' => 'M050', 'full_name' => 'Juma Ally', 'phone' => '0754000000', 'joined_at' => '2020-01-01', 'entry_fee_paid' => '1',
        ])->assertRedirect();

        $juma = $this->member('M050');
        $this->assertSame('active', $juma->status);
        $this->assertSame(0, $juma->fees()->count());
    }

    public function test_member_owing_more_than_grace_months_becomes_inactive_and_is_hidden_from_active_list(): void
    {
        $baraka = $this->member('M002');
        $this->owePastMonths($baraka, 3);

        $this->actingAs($this->officer('secretary'))->post('/members/enforce')->assertRedirect();

        $this->assertSame('inactive', $baraka->fresh()->status);
        $this->assertSame('active', $this->member('M001')->status, 'One past month owed is within the grace period.');
        $change = $baraka->statusChanges()->latest('id')->first();
        $this->assertSame('Hajalipa ada kwa miezi 3', __($change->reason, $change->reason_params));
        Queue::assertPushed(SendSmsNotification::class, fn ($job) => $job->msisdn === $baraka->phone);

        $officer = $this->officer('secretary');
        $this->actingAs($officer)->get('/members')->assertDontSee('Baraka Mussa');
        $this->actingAs($officer)->get('/members?status=inactive')->assertSee('Baraka Mussa');
    }

    public function test_inactive_member_has_no_ussd_and_cannot_be_an_event_beneficiary(): void
    {
        $baraka = $this->member('M002');
        app(MembershipService::class)->changeStatus($baraka, 'inactive', 'Majaribio');
        config(['beem.ussd_token' => 't']);

        $reply = $this->postJson('/integrations/beem/ussd/t', [
            'command' => 'initiate', 'msisdn' => $baraka->phone, 'operator' => 'x', 'session_id' => 'S9', 'payload' => ['response' => '0'],
        ])->json();
        $this->assertSame('terminate', $reply['command']);
        $this->assertStringContainsString('si mwanachama hai', $reply['payload']['request']);

        $this->actingAs($this->officer('secretary'))->post('/events', [
            'event_type' => 'illness', 'title' => 'Matibabu', 'beneficiary_member_id' => $baraka->id, 'status' => 'active',
        ])->assertSessionHasErrors('beneficiary_member_id');
    }

    public function test_reinstatement_needs_the_fee_and_all_arrears(): void
    {
        $baraka = $this->member('M002');
        $this->owePastMonths($baraka, 3);
        app(MembershipService::class)->enforce(1);

        $this->actingAs($this->officer('secretary'))->post("/members/{$baraka->id}/reinstatement")->assertRedirect();
        $fee = $baraka->fees()->where('kind', 'reinstatement')->firstOrFail();

        $this->pay($baraka, 'membership_fee', $fee->id, 20000, 'RISITI-R1');
        $this->assertSame('inactive', $baraka->fresh()->status, 'Arrears are still owed.');

        foreach ($baraka->obligations as $i => $obligation) {
            $this->pay($baraka, 'dues', $obligation->id, 10000, "RISITI-D{$i}");
        }

        $this->assertSame('active', $baraka->fresh()->status);
        $this->assertSame(['inactive', 'inactive', 'active'], $baraka->statusChanges()->orderBy('id')->pluck('to_status')->all());
    }

    public function test_reinstatement_without_arrears_rule_needs_only_the_fee(): void
    {
        Settings::set(['reinstate_requires_arrears' => '0']);
        $baraka = $this->member('M002');
        $this->owePastMonths($baraka, 3);
        app(MembershipService::class)->enforce(1);

        $fee = app(MembershipService::class)->startReinstatement($baraka->fresh());
        $this->pay($baraka, 'membership_fee', $fee->id, 20000, 'RISITI-R2');

        $this->assertSame('active', $baraka->fresh()->status);
    }

    public function test_officer_can_suspend_and_lift_with_history(): void
    {
        $neema = $this->member('M003');
        $secretary = $this->officer('secretary');
        $form = ['member_number' => 'M003', 'full_name' => $neema->full_name, 'phone' => $neema->phone, 'joined_at' => '2025-01-01'];

        $this->actingAs($secretary)->put("/members/{$neema->id}", $form + ['status_action' => 'suspended', 'status_reason' => 'Nidhamu'])->assertRedirect();
        $this->assertSame('suspended', $neema->fresh()->status);

        $this->actingAs($secretary)->put("/members/{$neema->id}", $form + ['status_action' => 'active'])->assertRedirect();
        $this->assertSame('active', $neema->fresh()->status);
        $this->assertSame('Nidhamu', $neema->statusChanges()->orderBy('id')->value('reason'));
    }

    public function test_photo_is_stored_privately_and_shown_to_officers(): void
    {
        Storage::fake('local');
        $neema = $this->member('M003');

        $this->actingAs($this->officer('secretary'))->put("/members/{$neema->id}", [
            'member_number' => 'M003', 'full_name' => $neema->full_name, 'phone' => $neema->phone, 'joined_at' => '2025-01-01',
            'photo' => UploadedFile::fake()->image('neema.jpg'),
        ])->assertRedirect();

        $path = $neema->fresh()->photo_path;
        Storage::disk('local')->assertExists($path);
        $this->actingAs($this->officer('chair'))->get("/members/{$neema->id}/photo")->assertOk();
        $this->actingAs($this->officer('treasurer'))->get("/members/{$neema->id}/photo")->assertForbidden();
    }

    public function test_chair_sees_everything_but_cannot_change_anything(): void
    {
        $chair = $this->officer('chair');
        $member = $this->member('M001');

        foreach (['/dashboard', '/members', "/members/{$member->id}", '/dues', '/savings', '/payments', '/events', '/announcements'] as $url) {
            $this->actingAs($chair)->get($url)->assertOk();
        }

        $this->actingAs($chair)->get('/members/create')->assertForbidden();
        $this->actingAs($chair)->get('/payments/create')->assertForbidden();
        $this->actingAs($chair)->get('/events/create')->assertForbidden();
        $this->actingAs($chair)->post('/members/enforce')->assertForbidden();
        $this->actingAs($chair)->get("/members/{$member->id}")->assertDontSee('Weka PIN')->assertDontSee('Hariri');
    }

    public function test_dashboard_shows_inactive_members_debt_and_debtors(): void
    {
        $baraka = $this->member('M002');
        $this->owePastMonths($baraka, 3);
        app(MembershipService::class)->enforce(1);

        $this->actingAs($this->officer('chair'))->get('/dashboard')
            ->assertSee('Wasio hai')
            ->assertSee('Jumla ya madeni')
            ->assertSee('TSh 50,000')
            ->assertSee('Baraka Mussa');
    }
}
