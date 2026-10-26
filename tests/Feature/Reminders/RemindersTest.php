<?php

namespace Tests\Feature\Reminders;

use App\Jobs\SendSmsNotification;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    private function officer(string $role = 'treasurer'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    private function member(string $number): Member
    {
        return Member::query()->where('member_number', $number)->firstOrFail();
    }

    private function runOn(string $date): array
    {
        $this->travelTo(CarbonImmutable::parse($date.' 08:00'));

        return app(ReminderService::class)->runDaily(1);
    }

    private function payDues(Member $member, string $period, string $receipt): void
    {
        $obligation = Obligation::query()->where('member_id', $member->id)->where('period', $period)->firstOrFail();

        $this->actingAs($this->officer())->post('/payments', [
            'credited_member_id' => $member->id, 'target_type' => 'dues', 'obligation_id' => $obligation->id,
            'amount' => $obligation->amount_due, 'method' => 'cash', 'transaction_id' => $receipt,
        ])->assertRedirect();
    }

    private function smsTo(Member $member, string $contains): int
    {
        return Queue::pushed(SendSmsNotification::class, fn ($job) => $job->msisdn === $member->phone && str_contains($job->message, $contains))->count();
    }

    public function test_daily_run_creates_the_months_dues_and_reminds_once_inside_the_window(): void
    {
        $this->assertSame(0, $this->runOn('2026-11-06')['dues_reminders'], 'Too early: the 10th minus 3 days is the 7th.');
        $this->assertSame(6, Obligation::query()->where('period', '2026-11')->count(), 'Dues for November are created on the first run.');

        $this->payDues($this->member('M002'), '2026-11', 'R-1');

        $this->assertSame(5, $this->runOn('2026-11-07')['dues_reminders']);
        $this->assertSame(0, $this->runOn('2026-11-08')['dues_reminders'], 'Each member is reminded only once.');

        $amina = $this->member('M001');
        $this->assertSame(1, $this->smsTo($amina, 'kabla ya 10/11/2026'));
        $this->assertSame(0, $this->smsTo($this->member('M002'), 'Ukumbusho'));
    }

    public function test_overdue_notice_goes_after_the_deadline_with_the_full_debt(): void
    {
        $this->runOn('2026-11-01');
        $this->assertSame(0, $this->runOn('2026-11-10')['dues_overdue'], 'The deadline day itself is not late.');

        $sent = $this->runOn('2026-11-11')['dues_overdue'];

        $this->assertSame(6, $sent);
        // Amina owes September, October and November.
        $this->assertSame(1, $this->smsTo($this->member('M001'), 'Deni lako lote ni TSh 30,000'));
        $this->assertSame(0, $this->runOn('2026-11-12')['dues_overdue']);
    }

    public function test_reminders_can_be_switched_off(): void
    {
        Settings::set(['reminders_enabled' => '0']);

        $this->assertSame(0, array_sum($this->runOn('2026-11-08')));
        Queue::assertNothingPushed();
    }

    public function test_event_with_amount_per_member_shows_debts_and_reminds_before_its_end(): void
    {
        $secretary = $this->officer('secretary');
        $groom = $this->member('M006');

        $this->actingAs($secretary)->post('/events', [
            'event_type' => 'wedding', 'title' => 'Harusi ya Joseph', 'beneficiary_member_id' => $groom->id,
            'amount_per_member' => 5000, 'target_amount' => 30000, 'starts_at' => '2026-10-20', 'ends_at' => '2026-10-30', 'status' => 'active',
        ])->assertRedirect();

        $event = ContributionEvent::query()->where('title', 'Harusi ya Joseph')->firstOrFail();
        $this->assertSame('23:59:59', $event->ends_at->format('H:i:s'), 'The last day still counts.');

        $this->actingAs($secretary)->get("/events/{$event->id}")
            ->assertSee('Kila mwanachama')
            ->assertSee('5 wanadaiwa TSh 25,000', false)
            ->assertDontSee('Joseph Mrema</span>', false);

        $this->assertSame(5, $this->runOn('2026-10-28')['event_reminders']);
        $this->assertSame(0, $this->smsTo($groom, 'Harusi'), 'The beneficiary is not asked to contribute.');
        $this->assertSame(1, $this->smsTo($this->member('M001'), 'mchango wa Harusi ya Joseph TSh 5,000 kabla ya 30/10/2026'));
    }

    public function test_debt_notices_list_each_part_and_are_sent_once_a_day(): void
    {
        $treasurer = $this->officer();

        $this->actingAs($treasurer)->post('/reminders/debts')->assertRedirect()->assertSessionHas('status', 'Taarifa za deni zimetumwa kwa wadaiwa 1 kwa SMS.');
        $this->assertSame(1, $this->smsTo($this->member('M001'), 'Ada TSh 20,000. Jumla TSh 20,000'));

        $this->actingAs($treasurer)->post('/reminders/debts')->assertSessionHas('status', 'Hakuna SMS iliyotumwa: hakuna mdaiwa, au wote walishatumiwa leo.');
        $this->actingAs($this->officer('chair'))->post('/reminders/debts')->assertForbidden();
    }

    public function test_receipt_says_what_was_paid_and_what_is_left(): void
    {
        $amina = $this->member('M001');
        $this->payDues($amina, now()->subMonth()->format('Y-m'), 'R-9');

        $month = now()->subMonth()->locale('sw')->translatedFormat('F Y');
        $this->assertSame(1, $this->smsTo($amina, "Tumepokea TSh 10,000 kwa ada ya {$month}. Kumbukumbu"));
        $this->assertSame(1, $this->smsTo($amina, 'Deni lililobaki: TSh 10,000'));
    }

    public function test_ussd_lists_other_contributions_and_suggests_what_is_owed(): void
    {
        ContributionEvent::query()->create([
            'group_id' => 1, 'event_type' => 'development', 'title' => 'Ujenzi wa ofisi', 'amount_per_member' => 7000,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), 'status' => 'active',
        ]);
        config(['beem.ussd_token' => 't']);

        $send = fn (string $command, string $input) => $this->postJson('/integrations/beem/ussd/t', [
            'command' => $command, 'msisdn' => '255700000001', 'operator' => 'x', 'session_id' => 'E1', 'payload' => ['response' => $input],
        ])->json('payload.request');

        $send('initiate', '0');
        $this->assertStringContainsString('8. Michango mingine', $send('continue', '1234'));
        $this->assertStringContainsString('1. Ujenzi wa ofisi', $send('continue', '8'));
        $this->assertStringContainsString('Kiasi kinachodaiwa TSh 7,000', $send('continue', '1'));
    }
}
