<?php

namespace App\Services\Ussd;

use App\Models\Announcement;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\UssdSession;
use App\Services\Balances\BalanceService;
use App\Services\Payments\PaymentIntentService;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

/**
 * Provider-neutral USSD state machine. Each call receives the subscriber's
 * latest input and returns the next screen. State lives in ussd_sessions.
 */
class UssdMenuService
{
    private const MAIN_MENU = "1. Ada\n2. Ugonjwa\n3. Msiba\n4. Akiba yangu\n5. Akiba ya kikundi\n6. Matangazo\n7. Changia rafiki\n0. Toka";

    private const EVENT_TYPES = ['2' => 'illness', '3' => 'bereavement'];

    public function __construct(
        private BalanceService $balances,
        private PaymentIntentService $intents,
    ) {}

    public function handle(UssdSession $session, string $input): UssdReply
    {
        $input = trim($input);
        $reply = $session->state === 'start' ? $this->start($session) : $this->dispatch($session, $input);

        if ($reply->end) {
            $session->update(['state' => 'ended', 'ended_at' => now()]);
        }

        return $reply;
    }

    private function dispatch(UssdSession $session, string $input): UssdReply
    {
        if ($session->state !== 'pin' && ! $session->authenticated) {
            return UssdReply::end('Kipindi kimeisha. Tafadhali piga tena.');
        }

        return match ($session->state) {
            'pin' => $this->checkPin($session, $input),
            'main' => $this->mainMenu($session, $input),
            'dues_select' => $this->duesSelect($session, $input),
            'event_select' => $this->eventSelect($session, $input),
            'savings' => $this->savingsMenu($session, $input),
            'friend_number' => $this->friendNumber($session, $input),
            'friend_purpose' => $this->friendPurpose($session, $input),
            'amount' => $this->amount($session, $input),
            'confirm' => $this->confirm($session, $input),
            'back_only' => $input === '0' ? $this->showMain($session) : UssdReply::end('Asante kwa kutumia '.Settings::systemName().'.'),
            default => UssdReply::end('Kipindi kimeisha. Tafadhali piga tena.'),
        };
    }

    private function start(UssdSession $session): UssdReply
    {
        $member = Member::query()
            ->where('group_id', config('beem.ussd_group_id'))
            ->where('phone', Phone::normalise($session->msisdn))
            ->first();

        $name = Settings::systemName();

        if (! $member || ! $member->isActive()) {
            return UssdReply::end("Karibu {$name}.\nNamba hii haijasajiliwa. Wasiliana na katibu wa kikundi.");
        }

        if (! $member->pin_hash) {
            return UssdReply::end("Karibu {$name}.\nHujawekewa PIN bado. Wasiliana na katibu wa kikundi.");
        }

        if ($member->pin_locked_until?->isFuture()) {
            return UssdReply::end('PIN imefungwa kwa muda. Jaribu tena baadaye.');
        }

        $session->update(['member_id' => $member->id, 'state' => 'pin']);

        return UssdReply::con("Karibu {$name}\nWeka PIN yako:");
    }

    private function checkPin(UssdSession $session, string $input): UssdReply
    {
        $member = $session->member;

        if ($member->pin_locked_until?->isFuture()) {
            return UssdReply::end('PIN imefungwa kwa muda. Jaribu tena baadaye.');
        }

        if (Hash::check($input, $member->pin_hash)) {
            $member->update(['pin_failed_attempts' => 0, 'pin_locked_until' => null]);
            $session->update(['authenticated' => true]);

            return $this->showMain($session);
        }

        $attempts = $member->pin_failed_attempts + 1;
        $max = config('beem.ussd_pin_max_attempts');

        if ($attempts >= $max) {
            $member->update([
                'pin_failed_attempts' => 0,
                'pin_locked_until' => now()->addMinutes(config('beem.ussd_pin_lock_minutes')),
            ]);

            return UssdReply::end('PIN si sahihi mara nyingi. Imefungwa kwa muda.');
        }

        $member->update(['pin_failed_attempts' => $attempts]);

        return UssdReply::con('PIN si sahihi. Jaribu tena ('.($max - $attempts).' yamebaki):');
    }

    private function showMain(UssdSession $session): UssdReply
    {
        $session->update(['state' => 'main', 'data' => []]);

        return UssdReply::con(Settings::systemName()."\n".self::MAIN_MENU);
    }

    private function mainMenu(UssdSession $session, string $input): UssdReply
    {
        $member = $session->member;

        return match ($input) {
            '1' => $this->showDues($session),
            '2', '3' => $this->showEvents($session, self::EVENT_TYPES[$input]),
            '4' => $this->showSavings($session),
            '5' => $this->backOnly($session, 'Akiba ya kikundi: '.Money::format($this->balances->groupSavings($member->group_id), $member->group->currency)),
            '6' => $this->showAnnouncements($session),
            '7' => $this->goTo($session, 'friend_number', [], 'Weka namba ya uanachama ya rafiki:'),
            '0' => UssdReply::end('Asante kwa kutumia '.Settings::systemName().'.'),
            default => UssdReply::con("Chaguo si sahihi.\n".self::MAIN_MENU),
        };
    }

    private function showDues(UssdSession $session): UssdReply
    {
        $rows = $this->balances->unpaidObligations($session->member);

        if ($rows->isEmpty()) {
            return $this->backOnly($session, 'Huna deni la ada. Asante!');
        }

        $options = [];
        $lines = ['Chagua mwezi:'];

        foreach ($rows as $i => $row) {
            $options[$i + 1] = ['id' => $row['obligation']->id, 'outstanding' => $row['outstanding'], 'label' => 'Ada '.$row['obligation']->period];
            $lines[] = ($i + 1).'. '.$row['obligation']->period.' - '.Money::format($row['outstanding']);
        }

        $lines[] = '0. Rudi';

        return $this->goTo($session, 'dues_select', ['options' => $options], implode("\n", $lines));
    }

    private function duesSelect(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showMain($session);
        }

        $option = $session->data['options'][$input] ?? null;

        if (! $option) {
            return UssdReply::con('Chaguo si sahihi. Chagua tena au 0 kurudi:');
        }

        return $this->askAmount($session, [
            'target_type' => 'dues',
            'target_id' => $option['id'],
            'credited_member_id' => $session->member_id,
            'label' => $option['label'],
            'suggested' => $option['outstanding'],
        ]);
    }

    private function showEvents(UssdSession $session, string $type): UssdReply
    {
        $events = ContributionEvent::query()
            ->where('group_id', $session->member->group_id)
            ->where('event_type', $type)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()
            ->take(5)
            ->get();

        if ($events->isEmpty()) {
            return $this->backOnly($session, $type === 'illness' ? 'Hakuna mchango wa ugonjwa kwa sasa.' : 'Hakuna mchango wa msiba kwa sasa.');
        }

        $options = [];
        $lines = ['Chagua mchango:'];

        foreach ($events->values() as $i => $event) {
            $options[$i + 1] = ['id' => $event->id, 'label' => $event->title];
            $lines[] = ($i + 1).'. '.$event->title;
        }

        $lines[] = '0. Rudi';

        return $this->goTo($session, 'event_select', ['options' => $options], implode("\n", $lines));
    }

    private function eventSelect(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showMain($session);
        }

        $option = $session->data['options'][$input] ?? null;

        if (! $option) {
            return UssdReply::con('Chaguo si sahihi. Chagua tena au 0 kurudi:');
        }

        return $this->askAmount($session, [
            'target_type' => 'event',
            'target_id' => $option['id'],
            'credited_member_id' => $session->member_id,
            'label' => $option['label'],
        ]);
    }

    private function showSavings(UssdSession $session): UssdReply
    {
        $member = $session->member;
        $balance = Money::format($this->balances->memberSavings($member), $member->group->currency);

        return $this->goTo($session, 'savings', [], "Akiba yako: {$balance}\n1. Weka akiba\n0. Rudi");
    }

    private function savingsMenu(UssdSession $session, string $input): UssdReply
    {
        return match ($input) {
            '1' => $this->askAmount($session, [
                'target_type' => 'savings',
                'target_id' => null,
                'credited_member_id' => $session->member_id,
                'label' => 'Akiba',
            ]),
            '0' => $this->showMain($session),
            default => UssdReply::con("Chaguo si sahihi.\n1. Weka akiba\n0. Rudi"),
        };
    }

    private function friendNumber(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showMain($session);
        }

        $friend = Member::query()
            ->where('group_id', $session->member->group_id)
            ->where('member_number', strtoupper($input))
            ->where('status', 'active')
            ->first();

        if (! $friend) {
            return UssdReply::con("Mwanachama hakupatikana.\nWeka namba tena au 0 kurudi:");
        }

        return $this->goTo($session, 'friend_purpose', ['friend_id' => $friend->id, 'friend_name' => $friend->full_name],
            "Mchangie {$friend->full_name}:\n1. Ada\n2. Akiba\n0. Rudi");
    }

    private function friendPurpose(UssdSession $session, string $input): UssdReply
    {
        $data = $session->data;

        if ($input === '0') {
            return $this->showMain($session);
        }

        if ($input === '2') {
            return $this->askAmount($session, [
                'target_type' => 'savings',
                'target_id' => null,
                'credited_member_id' => $data['friend_id'],
                'label' => 'Akiba ya '.$data['friend_name'],
            ]);
        }

        if ($input === '1') {
            $friend = Member::query()->findOrFail($data['friend_id']);
            $row = $this->balances->unpaidObligations($friend, 1)->first();

            if (! $row) {
                return $this->backOnly($session, "{$friend->full_name} hana deni la ada.");
            }

            return $this->askAmount($session, [
                'target_type' => 'dues',
                'target_id' => $row['obligation']->id,
                'credited_member_id' => $friend->id,
                'label' => 'Ada '.$row['obligation']->period.' ya '.$friend->full_name,
                'suggested' => $row['outstanding'],
            ]);
        }

        return UssdReply::con("Chaguo si sahihi.\n1. Ada\n2. Akiba\n0. Rudi");
    }

    private function askAmount(UssdSession $session, array $payment): UssdReply
    {
        $prompt = isset($payment['suggested'])
            ? 'Kiasi kinachodaiwa '.Money::format($payment['suggested']).".\nWeka kiasi:"
            : 'Weka kiasi (TSh):';

        return $this->goTo($session, 'amount', ['payment' => $payment], $prompt);
    }

    private function amount(UssdSession $session, string $input): UssdReply
    {
        $min = (int) Settings::get('ussd_min_amount');
        $max = (int) Settings::get('ussd_max_amount');

        if (! ctype_digit($input) || (int) $input < $min || (int) $input > $max) {
            return UssdReply::con('Kiasi si sahihi. Weka kati ya '.Money::format($min).' na '.Money::format($max).':');
        }

        $data = $session->data;
        $data['payment']['amount'] = (int) $input;
        $session->update(['state' => 'confirm', 'data' => $data]);

        return UssdReply::con("Thibitisha:\n{$data['payment']['label']}\nKiasi: ".Money::format((int) $input)."\n1. Thibitisha\n0. Ghairi");
    }

    private function confirm(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return UssdReply::end('Umeghairi. Hakuna malipo yaliyofanyika.');
        }

        if ($input !== '1') {
            return UssdReply::con("Chaguo si sahihi.\n1. Thibitisha\n0. Ghairi");
        }

        $payment = $session->data['payment'];
        $payer = $session->member;
        $credited = Member::query()->where('group_id', $payer->group_id)->findOrFail($payment['credited_member_id']);

        $intent = $this->intents->create(
            payer: $payer,
            credited: $credited,
            targetType: $payment['target_type'],
            targetId: $payment['target_id'],
            amount: $payment['amount'],
            idempotencyKey: 'ussd:'.$session->session_id,
            payerMsisdn: $session->msisdn,
        );

        return UssdReply::end("Ombi limepokelewa.\nKumbukumbu: {$intent->reference}\nMalipo yatathibitishwa na mweka hazina. Utapata SMS.");
    }

    private function showAnnouncements(UssdSession $session): UssdReply
    {
        $items = Announcement::query()
            ->where('group_id', $session->member->group_id)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('published_at')
            ->take(2)
            ->get();

        if ($items->isEmpty()) {
            return $this->backOnly($session, 'Hakuna matangazo kwa sasa.');
        }

        return $this->backOnly($session, $items->map(fn ($a) => $a->title.': '.str($a->body)->limit(60))->implode("\n"));
    }

    private function backOnly(UssdSession $session, string $text): UssdReply
    {
        return $this->goTo($session, 'back_only', [], $text."\n0. Rudi");
    }

    private function goTo(UssdSession $session, string $state, array $data, string $text): UssdReply
    {
        $session->update(['state' => $state, 'data' => array_merge($session->data ?? [], $data)]);

        return UssdReply::con($text);
    }
}
