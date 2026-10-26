<?php

namespace Tests\Feature\Integrations\Beem;

use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UssdSessionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['beem.ussd_token' => self::TOKEN, 'beem.ussd_group_id' => 1]);
        $this->seed(DatabaseSeeder::class);
    }

    private function ussd(string $command, string $response = '0', string $session = 'S1', string $msisdn = '255700000001', string $token = self::TOKEN)
    {
        return $this->postJson("/integrations/beem/ussd/{$token}", [
            'command' => $command,
            'msisdn' => $msisdn,
            'operator' => 'vodacom',
            'session_id' => $session,
            'payload' => ['request_id' => 0, 'response' => $response],
        ]);
    }

    private function text($response): string
    {
        return $response->json('payload.request');
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->ussd('initiate', token: 'wrong')->assertNotFound();
    }

    public function test_unregistered_number_is_told_to_contact_secretary(): void
    {
        $response = $this->ussd('initiate', msisdn: '255799999999');

        $response->assertOk()->assertJsonPath('command', 'terminate');
        $this->assertStringContainsString('haijasajiliwa', $this->text($response));
    }

    public function test_initiate_asks_for_pin_and_echoes_beem_fields(): void
    {
        $this->ussd('initiate')
            ->assertOk()
            ->assertJson([
                'msisdn' => '255700000001',
                'operator' => 'vodacom',
                'session_id' => 'S1',
                'command' => 'continue',
            ])
            ->assertJsonPath('payload.request', "Karibu Mfumo wa Kikundi\nWeka PIN yako:");
    }

    public function test_system_name_set_by_admin_appears_in_menu(): void
    {
        Settings::set(['system_name' => 'Umoja Welfare']);

        $this->assertStringContainsString('Karibu Umoja Welfare', $this->text($this->ussd('initiate')));
    }

    public function test_correct_pin_shows_main_menu(): void
    {
        $this->ussd('initiate');
        $response = $this->ussd('continue', '1234');

        $response->assertJsonPath('command', 'continue');
        $this->assertStringContainsString('1. Ada', $this->text($response));
        $this->assertStringContainsString('7. Changia rafiki', $this->text($response));
    }

    public function test_three_wrong_pins_lock_the_member(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '0000');
        $this->ussd('continue', '1111');
        $this->ussd('continue', '2222')->assertJsonPath('command', 'terminate');

        $this->assertTrue(Member::query()->where('member_number', 'M001')->first()->pin_locked_until->isFuture());
        $this->ussd('initiate', session: 'S2')->assertJsonPath('command', 'terminate');
    }

    public function test_pay_dues_creates_one_pending_intent(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');
        $this->assertStringContainsString('TSh 10,000', $this->text($this->ussd('continue', '1')));
        $this->ussd('continue', '1');
        $this->assertStringContainsString('Thibitisha', $this->text($this->ussd('continue', '6000')));

        $response = $this->ussd('continue', '1');

        $response->assertJsonPath('command', 'terminate');
        $this->assertStringContainsString('Kumbukumbu', $this->text($response));

        $intent = PaymentIntent::query()->sole();
        $this->assertSame('pending', $intent->status);
        $this->assertSame('dues', $intent->target_type);
        $this->assertSame(6000, (int) $intent->amount);

        // A repeated confirm on the ended session must not create a second request.
        $this->ussd('continue', '1')->assertJsonPath('command', 'terminate');
        $this->assertSame(1, PaymentIntent::query()->count());
    }

    public function test_amount_below_minimum_is_rejected(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');
        $this->ussd('continue', '4');
        $this->ussd('continue', '1');

        $this->assertStringContainsString('Kiasi si sahihi', $this->text($this->ussd('continue', '10')));
        $this->assertSame(0, PaymentIntent::query()->count());
    }

    public function test_contribute_for_friend_credits_the_friend(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');
        $this->ussd('continue', '7');
        $this->assertStringContainsString('Baraka Mussa', $this->text($this->ussd('continue', 'm002')));
        $this->ussd('continue', '2');
        $this->ussd('continue', '5000');
        $this->ussd('continue', '1');

        $intent = PaymentIntent::query()->sole();
        $baraka = Member::query()->where('member_number', 'M002')->first();
        $amina = Member::query()->where('member_number', 'M001')->first();

        $this->assertSame($baraka->id, $intent->credited_member_id);
        $this->assertSame($amina->id, $intent->payer_member_id);
        $this->assertSame('savings', $intent->target_type);
    }

    public function test_illness_event_and_announcements_are_listed(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');
        $this->assertStringContainsString('Matibabu ya Mzee Hamisi', $this->text($this->ussd('continue', '2')));
        $this->ussd('continue', '0');
        $this->assertStringContainsString('Kikao', $this->text($this->ussd('continue', '6')));
    }

    public function test_invalid_choice_keeps_session_open(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');

        // 9 switches language, so use a number that is not on the menu.
        $response = $this->ussd('continue', '42');

        $response->assertJsonPath('command', 'continue');
        $this->assertStringContainsString('Chaguo si sahihi', $this->text($response));
    }

    public function test_menu_screens_fit_ussd_length(): void
    {
        Settings::set(['system_name' => str_repeat('A', 40)]);

        // Swahili, then English (the shipped default), with the longest system name allowed.
        foreach (['sw' => 'S1', 'en' => 'S2'] as $locale => $session) {
            Member::query()->where('phone', '255700000001')->update(['locale' => $locale]);
            $this->ussd('initiate', session: $session);
            $menu = $this->text($this->ussd('continue', '1234', session: $session));

            $this->assertLessThanOrEqual(182, mb_strlen($menu), "Main menu too long in {$locale}: ".mb_strlen($menu));
        }
    }

    public function test_long_contribution_titles_still_fit_one_screen(): void
    {
        foreach (range(1, 5) as $i) {
            ContributionEvent::query()->create([
                'group_id' => 1, 'event_type' => 'illness', 'title' => str_repeat('Matibabu ya mwanachama ', 2).$i, 'status' => 'active',
            ]);
        }

        foreach (['sw' => 'S9', 'en' => 'S10'] as $locale => $session) {
            Member::query()->where('phone', '255700000001')->update(['locale' => $locale]);
            $this->ussd('initiate', session: $session);
            $this->ussd('continue', '1234', session: $session);

            $screen = $this->text($this->ussd('continue', '2', session: $session));
            $this->assertStringContainsString('5. ', $screen);
            $this->assertLessThanOrEqual(182, mb_strlen($screen), "Contribution list too long in {$locale}: ".mb_strlen($screen));
        }
    }

    public function test_beem_documented_payload_with_numeric_values_is_accepted(): void
    {
        // Exactly as in Beem's documentation: response is the number 0.
        $this->postJson('/integrations/beem/ussd/'.self::TOKEN, [
            'command' => 'initiate',
            'msisdn' => 255700000001,
            'session_id' => 4574,
            'operator' => 'vodacom',
            'payload' => ['request_id' => 0, 'response' => 0],
        ])->assertOk()
            ->assertJsonPath('command', 'continue')
            ->assertJsonPath('msisdn', '255700000001')
            ->assertJsonPath('session_id', '4574')
            ->assertJsonPath('operator', 'vodacom');
    }

    public function test_session_id_reused_by_another_phone_is_refused(): void
    {
        $this->ussd('initiate');
        $this->ussd('continue', '1234');

        $this->ussd('continue', '1', msisdn: '255700000002')->assertJsonPath('command', 'terminate');
    }
}
