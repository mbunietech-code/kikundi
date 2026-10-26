<?php

namespace Tests\Feature;

use App\Jobs\SendSmsNotification;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Reminders\ReminderService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The shipped default: English, with Swahili one click away.
        config(['app.locale' => 'en', 'beem.ussd_group_id' => 1, 'beem.ussd_token' => 't']);
        app()->setLocale('en');
        $this->seed(DatabaseSeeder::class);
    }

    private function officer(string $role = 'system_admin'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    private function member(string $number): Member
    {
        return Member::query()->where('member_number', $number)->firstOrFail();
    }

    public function test_pages_are_english_by_default(): void
    {
        $this->get('/login')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Welcome back')->assertDontSee('Karibu tena');

        $this->actingAs($this->officer())->get('/dashboard')->assertOk()
            ->assertSee('Active members')->assertSee('Group System')->assertDontSee('Wanachama hai');
    }

    public function test_switch_button_changes_language_and_remembers_it(): void
    {
        $this->get('/login')->assertSee(route('locale.switch', 'sw'), false);

        $this->from('/login')->get('/lang/sw')->assertRedirect('/login')->assertCookie('locale', 'sw');
        $this->get('/login')->assertSee('<html lang="sw"', false)->assertSee('Karibu tena');

        $this->get('/lang/en');
        $this->get('/login')->assertSee('Welcome back');

        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_group_default_language_can_be_set_to_swahili(): void
    {
        $this->actingAs($this->officer())->put('/admin/settings', [
            'system_name' => 'Mfumo wa Kikundi', 'ussd_min_amount' => 500, 'ussd_max_amount' => 5000000, 'default_locale' => 'sw',
        ])->assertRedirect();

        $this->get('/mwanachama/ingia')->assertSee('Karibu, mwanachama');
    }

    public function test_validation_messages_follow_the_language(): void
    {
        $admin = $this->officer();

        $this->actingAs($admin)->post('/announcements', ['body' => 'x'])->assertSessionHasErrors(['title' => 'The title field is required.']);

        $this->get('/lang/sw');
        $this->actingAs($admin)->post('/announcements', ['body' => 'x'])->assertSessionHasErrors(['title' => 'kichwa inahitajika.']);
    }

    public function test_member_language_choice_is_saved_for_sms_and_ussd(): void
    {
        $this->post('/mwanachama/ingia', ['phone' => '0700000001', 'pin' => '1234'])->assertRedirect('/mwanachama');
        $this->get('/mwanachama')->assertSee('I owe');

        $this->get('/lang/sw');

        $this->assertSame('sw', $this->member('M001')->locale);
        $this->get('/mwanachama')->assertSee('Deni langu');
    }

    public function test_sms_reminders_use_each_members_own_language(): void
    {
        Queue::fake();
        $this->member('M002')->update(['locale' => 'sw']);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 08:00'));

        app(ReminderService::class)->runDaily(1);

        $sms = fn (string $phone) => Queue::pushed(SendSmsNotification::class, fn ($job) => $job->msisdn === $phone)->first()->message;
        $this->assertSame('Reminder: November dues of TSh 10,000 are due by 10/11/2026. Group System', $sms('255700000001'));
        $this->assertStringStartsWith('Ukumbusho: ada ya Novemba TSh 10,000 inatakiwa kulipwa kabla ya 10/11/2026.', $sms('255700000002'));
    }

    public function test_ussd_speaks_the_member_language_and_option_9_switches_it(): void
    {
        $send = fn (string $command, string $input) => $this->postJson('/integrations/beem/ussd/t', [
            'command' => $command, 'msisdn' => '255700000001', 'operator' => 'x', 'session_id' => 'L1', 'payload' => ['response' => $input],
        ])->json('payload.request');

        $this->assertStringContainsString('Enter your PIN', $send('initiate', '0'));
        $menu = $send('continue', '1234');
        $this->assertStringContainsString('1. Dues', $menu);
        $this->assertStringContainsString('9. Kiswahili', $menu);

        $this->assertStringContainsString('9. English', $send('continue', '9'));
        $this->assertSame('sw', $this->member('M001')->locale);
    }

    public function test_reports_and_history_are_translated(): void
    {
        $baraka = $this->member('M002');
        foreach ([1, 2, 3] as $back) {
            Obligation::query()->create(['group_id' => 1, 'member_id' => $baraka->id, 'period' => now()->subMonthsNoOverflow($back)->format('Y-m'), 'amount_due' => 10000]);
        }
        Queue::fake();
        app(MembershipService::class)->enforce(1);

        $admin = $this->officer();
        $this->actingAs($admin)->get("/members/{$baraka->id}")->assertSee('Has not paid dues for 3 months')->assertSee('Not an active member');
        $this->actingAs($admin)->get('/reports/debtors')->assertSee('Debtors report')->assertSee('Dues owed')->assertSee('Not an active member');

        $this->get('/lang/sw');
        $this->actingAs($admin)->get("/members/{$baraka->id}")->assertSee('Hajalipa ada kwa miezi 3');
    }

    public function test_officer_typed_reasons_are_shown_as_written(): void
    {
        $neema = $this->member('M003');
        app(MembershipService::class)->changeStatus($neema, 'suspended', 'Hakuhudhuria vikao vitatu', $this->officer()->id);

        $this->actingAs($this->officer())->get("/members/{$neema->id}")->assertSee('Hakuhudhuria vikao vitatu');
        $this->assertSame('', Settings::get('default_locale'));
    }
}
