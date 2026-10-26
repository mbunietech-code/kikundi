<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1, 'beem.ussd_token' => 't']);
        $this->seed([DatabaseSeeder::class, DemoActivitySeeder::class]);
    }

    private function member(string $number): Member
    {
        return Member::query()->where('member_number', $number)->firstOrFail();
    }

    private function login(string $phone, string $pin)
    {
        return $this->post('/mwanachama/ingia', ['phone' => $phone, 'pin' => $pin]);
    }

    public function test_member_signs_in_with_local_phone_and_ussd_pin_and_sees_own_details(): void
    {
        $this->login('0700 000 001', '1234')->assertRedirect('/mwanachama');

        $this->get('/mwanachama')->assertOk()
            ->assertSee('Amina Juma')
            ->assertSee('Deni langu')
            ->assertSee('TSh 20,000')
            ->assertSee('Kikao cha mwezi')
            ->assertDontSee('Rehema Kweka');
    }

    public function test_guests_are_sent_to_the_member_sign_in_page(): void
    {
        $this->get('/mwanachama')->assertRedirect('/mwanachama/ingia');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_wrong_pins_lock_the_portal_and_ussd_together(): void
    {
        foreach (range(1, 3) as $try) {
            $this->login('0700000001', '0000')->assertSessionHasErrors('phone');
        }

        $this->assertTrue($this->member('M001')->pin_locked_until->isFuture());
        $this->login('0700000001', '1234')->assertSessionHasErrors('phone');
        $this->assertGuest('member');

        $reply = $this->postJson('/integrations/beem/ussd/t', [
            'command' => 'initiate', 'msisdn' => '255700000001', 'operator' => 'x', 'session_id' => 'L1', 'payload' => ['response' => '0'],
        ])->json('payload.request');
        $this->assertStringContainsString('PIN imefungwa', $reply);
    }

    public function test_members_without_pin_or_who_departed_cannot_sign_in(): void
    {
        $this->login('0700000005', '1234')->assertSessionHasErrors('phone');

        $this->member('M002')->update(['status' => 'departed']);
        $this->login('0700000002', '1234')->assertSessionHasErrors('phone');
        $this->assertGuest('member');
    }

    public function test_member_cannot_open_officer_pages(): void
    {
        $this->login('0700000001', '1234');

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/members')->assertRedirect('/login');
    }

    public function test_member_downloads_own_statement_as_pdf(): void
    {
        $this->login('0700000003', '1234');

        $response = $this->get('/mwanachama/taarifa.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('mwanachama-m003-'.now()->year.'.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_member_changes_pin_used_by_ussd_too(): void
    {
        $this->login('0700000001', '1234');

        $this->put('/mwanachama/pin', ['current_pin' => '9999', 'pin' => '4321', 'pin_confirmation' => '4321'])->assertSessionHasErrors('current_pin');
        $this->put('/mwanachama/pin', ['current_pin' => '1234', 'pin' => '4321', 'pin_confirmation' => '4321'])->assertRedirect('/mwanachama');

        $this->assertTrue(Hash::check('4321', $this->member('M001')->pin_hash));
    }

    public function test_member_sign_out_keeps_an_officer_signed_in_on_the_same_browser(): void
    {
        User::factory()->create(['role' => 'treasurer', 'group_id' => 1, 'email' => 'hazina@example.test']);
        $this->post('/login', ['email' => 'hazina@example.test', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->login('0700000001', '1234');

        $this->post('/mwanachama/toka')->assertRedirect('/mwanachama/ingia');

        $this->get('/mwanachama')->assertRedirect('/mwanachama/ingia');

        // auth:member makes "member" the default guard for that request. Real
        // requests each boot a fresh app; tests share one, so reset it here.
        auth()->shouldUse('web');
        $this->get('/dashboard')->assertOk();
    }
}
