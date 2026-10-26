<?php

namespace Tests\Feature\Officer;

use App\Jobs\SendSmsNotification;
use App\Models\ContributionEvent;
use App\Models\Group;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OfficerPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed([DatabaseSeeder::class, DemoActivitySeeder::class]);
    }

    private function officer(string $role = 'system_admin'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    public function test_every_page_renders_for_the_system_admin(): void
    {
        $this->actingAs($this->officer());
        $member = Member::query()->first();
        $event = ContributionEvent::query()->first();

        foreach ([
            '/dashboard', '/members', '/members?q=amina', '/members/create', "/members/{$member->id}", "/members/{$member->id}/edit",
            '/dues', '/savings', '/events', '/events/create', "/events/{$event->id}", "/events/{$event->id}/edit",
            '/payments', '/payments?status=succeeded', '/payments/create', '/announcements', '/users', '/admin/settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_dashboard_shows_group_totals(): void
    {
        $this->actingAs($this->officer())->get('/dashboard')
            ->assertSee('Wanachama hai')
            ->assertSee('Malipo yanayosubiri')
            ->assertSee('Matibabu ya Mzee Hamisi');
    }

    public function test_treasurer_cannot_manage_members_and_secretary_cannot_see_payments(): void
    {
        $this->actingAs($this->officer('treasurer'))->get('/members')->assertForbidden();
        $this->actingAs($this->officer('treasurer'))->get('/payments')->assertOk();
        $this->actingAs($this->officer('secretary'))->get('/payments')->assertForbidden();
        $this->actingAs($this->officer('secretary'))->get('/admin/settings')->assertForbidden();
    }

    public function test_plain_members_cannot_enter_the_officer_portal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']))->get('/dashboard')->assertForbidden();
    }

    public function test_secretary_registers_a_member_with_local_phone_format(): void
    {
        $this->actingAs($this->officer('secretary'))->post('/members', [
            'member_number' => 'm010', 'full_name' => 'Asha Ali', 'phone' => '0712 345 678',
            'joined_at' => '2026-10-01', 'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('members', ['member_number' => 'M010', 'phone' => '255712345678']);
    }

    public function test_treasurer_verifies_a_pending_ussd_payment_once(): void
    {
        $intent = PaymentIntent::query()->where('status', 'pending')->first();
        $treasurer = $this->officer('treasurer');

        $this->actingAs($treasurer)->post("/payments/{$intent->id}/verify", ['method' => 'mpesa', 'transaction_id' => 'QWE123'])->assertRedirect();
        $this->actingAs($treasurer)->post("/payments/{$intent->id}/verify", ['method' => 'mpesa', 'transaction_id' => 'QWE123'])->assertRedirect();

        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertSame(1, Payment::query()->where('provider_transaction_id', 'QWE123')->count());
    }

    public function test_office_payment_is_recorded_against_the_chosen_month(): void
    {
        $member = Member::query()->where('member_number', 'M001')->first();
        $obligation = Obligation::query()->where('member_id', $member->id)->orderBy('period')->first();

        $this->actingAs($this->officer('treasurer'))->post('/payments', [
            'credited_member_id' => $member->id, 'target_type' => 'dues', 'obligation_id' => $obligation->id,
            'amount' => 4000, 'method' => 'cash', 'transaction_id' => 'RISITI-77',
        ])->assertRedirect('/payments?status=succeeded');

        $this->actingAs($this->officer())->get("/members/{$member->id}")->assertSee('TSh 4,000');
    }

    public function test_generating_dues_twice_does_not_duplicate(): void
    {
        $admin = $this->officer();
        $period = now()->addMonth()->format('Y-m');

        $this->actingAs($admin)->post('/dues/generate', ['period' => $period]);
        $this->actingAs($admin)->post('/dues/generate', ['period' => $period]);

        $this->assertSame(Member::query()->where('status', 'active')->count(), Obligation::query()->where('period', $period)->count());
    }

    public function test_announcement_with_sms_queues_one_message_per_active_member(): void
    {
        Queue::fake();

        $this->actingAs($this->officer('secretary'))->post('/announcements', [
            'title' => 'Kikao', 'body' => 'Jumamosi saa 4', 'publish' => 1, 'send_sms' => 1,
        ])->assertRedirect();

        Queue::assertPushed(SendSmsNotification::class, Member::query()->where('status', 'active')->count());
    }

    public function test_records_from_another_group_are_hidden(): void
    {
        $other = Group::query()->create(['name' => 'Kikundi B']);
        $stranger = Member::query()->create(['group_id' => $other->id, 'member_number' => 'X1', 'full_name' => 'Mgeni', 'phone' => '255799000000']);

        $this->actingAs($this->officer())->get("/members/{$stranger->id}")->assertNotFound();
    }
}
