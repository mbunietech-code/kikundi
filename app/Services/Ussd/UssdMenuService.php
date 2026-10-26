<?php

namespace App\Services\Ussd;

use App\Contracts\PaymentGateway;
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
use Illuminate\Support\Str;

/**
 * Provider-neutral USSD state machine. Each call receives the subscriber's
 * latest input and returns the next screen. State lives in ussd_sessions.
 */
class UssdMenuService
{
    /**
     * Translated as a whole; option 9 names the other language
     * ("9. English" in Swahili, "9. Kiswahili" in English).
     */
    public const MAIN_MENU = "1. Ada\n2. Ugonjwa\n3. Msiba\n4. Akiba yangu\n5. Akiba ya kikundi\n6. Matangazo\n7. Changia rafiki\n8. Michango mingine\n9. English\n0. Toka";

    /** Menu choice => event types listed under it. */
    private const EVENT_TYPES = ['2' => ['illness'], '3' => ['bereavement'], '8' => ['wedding', 'development', 'other']];

    /** Longest text a USSD screen shows reliably. */
    private const SCREEN_LIMIT = 182;

    private const NO_EVENTS = ['2' => 'Hakuna mchango wa ugonjwa kwa sasa.', '3' => 'Hakuna mchango wa msiba kwa sasa.', '8' => 'Hakuna mchango mwingine kwa sasa.'];

    public function __construct(
        private BalanceService $balances,
        private PaymentIntentService $intents,
        private PaymentGateway $gateway,
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
            return UssdReply::end(__('Kipindi kimeisha. Tafadhali piga tena.'));
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
            'back_only' => $input === '0' ? $this->showMain($session) : UssdReply::end(__('Asante kwa kutumia :system.', ['system' => Settings::systemName()])),
            default => UssdReply::end(__('Kipindi kimeisha. Tafadhali piga tena.')),
        };
    }

    private function start(UssdSession $session): UssdReply
    {
        $member = Member::query()
            ->where('group_id', config('beem.ussd_group_id'))
            ->where('phone', Phone::normalise($session->msisdn))
            ->first();

        $name = Settings::systemName();

        if (! $member || in_array($member->status, ['departed', 'suspended'], true)) {
            return UssdReply::end(__("Karibu :system.\nNamba hii haijasajiliwa. Wasiliana na katibu wa kikundi.", ['system' => $name]));
        }

        // Members who have lost or not yet gained active status have no USSD rights.
        if ($member->status === 'inactive') {
            return UssdReply::end(__(":system\nWewe si mwanachama hai kwa sasa. Wasiliana na mweka hazina ili kurejea.", ['system' => $name]));
        }

        if (! $member->isActive()) {
            return UssdReply::end(__("Karibu :system.\nUanachama wako utaanza ukilipa kiingilio. Wasiliana na mweka hazina.", ['system' => $name]));
        }

        if (! $member->pin_hash) {
            return UssdReply::end(__("Karibu :system.\nHujawekewa PIN bado. Wasiliana na katibu wa kikundi.", ['system' => $name]));
        }

        if ($member->pin_locked_until?->isFuture()) {
            return UssdReply::end(__('PIN imefungwa kwa muda. Jaribu tena baadaye.'));
        }

        $session->update(['member_id' => $member->id, 'state' => 'pin']);

        return UssdReply::con(__("Karibu :system\nWeka PIN yako:", ['system' => $name]));
    }

    private function checkPin(UssdSession $session, string $input): UssdReply
    {
        $member = $session->member;

        if ($member->pin_locked_until?->isFuture()) {
            return UssdReply::end(__('PIN imefungwa kwa muda. Jaribu tena baadaye.'));
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

            return UssdReply::end(__('PIN si sahihi mara nyingi. Imefungwa kwa muda.'));
        }

        $member->update(['pin_failed_attempts' => $attempts]);

        return UssdReply::con(__('PIN si sahihi. Jaribu tena (:left yamebaki):', ['left' => $max - $attempts]));
    }

    private function showMain(UssdSession $session): UssdReply
    {
        $session->update(['state' => 'main', 'data' => []]);

        return UssdReply::con(Settings::systemName()."\n".__(self::MAIN_MENU));
    }

    private function mainMenu(UssdSession $session, string $input): UssdReply
    {
        $member = $session->member;

        return match ($input) {
            '1' => $this->showDues($session),
            '2', '3', '8' => $this->showEvents($session, $input),
            '4' => $this->showSavings($session),
            '5' => $this->backOnly($session, __('Akiba ya kikundi: :amount', ['amount' => Money::format($this->balances->groupSavings($member->group_id), $member->group->currency)])),
            '6' => $this->showAnnouncements($session),
            '7' => $this->goTo($session, 'friend_number', [], __('Weka namba ya uanachama ya rafiki:')),
            '9' => $this->switchLanguage($session),
            '0' => UssdReply::end(__('Asante kwa kutumia :system.', ['system' => Settings::systemName()])),
            default => UssdReply::con(__('Chaguo si sahihi.')."\n".__(self::MAIN_MENU)),
        };
    }

    /** Swap between English and Swahili and remember it for SMS too. */
    private function switchLanguage(UssdSession $session): UssdReply
    {
        $locale = app()->getLocale() === 'sw' ? 'en' : 'sw';
        $session->member->update(['locale' => $locale]);
        app()->setLocale($locale);

        return $this->showMain($session);
    }

    private function showDues(UssdSession $session): UssdReply
    {
        $rows = $this->balances->unpaidObligations($session->member);

        if ($rows->isEmpty()) {
            return $this->backOnly($session, __('Huna deni la ada. Asante!'));
        }

        $options = [];
        $lines = [__('Chagua mwezi:')];

        foreach ($rows as $i => $row) {
            $options[$i + 1] = ['id' => $row['obligation']->id, 'outstanding' => $row['outstanding'], 'label' => __('Ada').' '.$row['obligation']->period];
            $lines[] = ($i + 1).'. '.$row['obligation']->period.' - '.Money::format($row['outstanding']);
        }

        $lines[] = __('0. Rudi');

        return $this->goTo($session, 'dues_select', ['options' => $options], implode("\n", $lines));
    }

    private function duesSelect(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showMain($session);
        }

        $option = $session->data['options'][$input] ?? null;

        if (! $option) {
            return UssdReply::con(__('Chaguo si sahihi. Chagua tena au 0 kurudi:'));
        }

        return $this->askAmount($session, [
            'target_type' => 'dues',
            'target_id' => $option['id'],
            'credited_member_id' => $session->member_id,
            'label' => $option['label'],
            'suggested' => $option['outstanding'],
        ]);
    }

    private function showEvents(UssdSession $session, string $choice): UssdReply
    {
        $events = ContributionEvent::query()
            ->where('group_id', $session->member->group_id)
            ->whereIn('event_type', self::EVENT_TYPES[$choice])
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()
            ->take(5)
            ->get();

        if ($events->isEmpty()) {
            return $this->backOnly($session, __(self::NO_EVENTS[$choice]));
        }

        $options = [];
        $lines = [__('Chagua mchango:')];

        $owed = $this->balances->eventDebts($session->member->group_id)->where('member_id', $session->member_id)->pluck('outstanding', 'event_id');
        $back = __('0. Rudi');

        // Titles are shortened only when the full list would not fit on one screen.
        $room = self::SCREEN_LIMIT - mb_strlen($lines[0]) - mb_strlen($back) - $events->count() * 4 - 1;
        $width = $events->sum(fn ($event) => mb_strlen($event->title)) > $room ? max(8, intdiv($room, $events->count()) - 3) : null;

        foreach ($events->values() as $i => $event) {
            $options[$i + 1] = ['id' => $event->id, 'label' => $event->title, 'outstanding' => $owed[$event->id] ?? null];
            $lines[] = ($i + 1).'. '.($width ? Str::limit($event->title, $width) : $event->title);
        }

        $lines[] = $back;

        return $this->goTo($session, 'event_select', ['options' => $options], implode("\n", $lines));
    }

    private function eventSelect(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showMain($session);
        }

        $option = $session->data['options'][$input] ?? null;

        if (! $option) {
            return UssdReply::con(__('Chaguo si sahihi. Chagua tena au 0 kurudi:'));
        }

        // A fixed per-member event suggests what is still owed.
        return $this->askAmount($session, array_filter([
            'target_type' => 'event',
            'target_id' => $option['id'],
            'credited_member_id' => $session->member_id,
            'label' => $option['label'],
            'suggested' => $option['outstanding'] ?? null,
        ], fn ($value) => $value !== null));
    }

    private function showSavings(UssdSession $session): UssdReply
    {
        $member = $session->member;
        $balance = Money::format($this->balances->memberSavings($member), $member->group->currency);

        return $this->goTo($session, 'savings', [], __("Akiba yako: :amount\n1. Weka akiba\n0. Rudi", ['amount' => $balance]));
    }

    private function savingsMenu(UssdSession $session, string $input): UssdReply
    {
        return match ($input) {
            '1' => $this->askAmount($session, [
                'target_type' => 'savings',
                'target_id' => null,
                'credited_member_id' => $session->member_id,
                'label' => __('Akiba'),
            ]),
            '0' => $this->showMain($session),
            default => UssdReply::con(__("Chaguo si sahihi.\n1. Weka akiba\n0. Rudi")),
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
            return UssdReply::con(__("Mwanachama hakupatikana.\nWeka namba tena au 0 kurudi:"));
        }

        // Shortened once here; long names would overflow the 182-character screens.
        $name = Str::limit($friend->full_name, 30);

        return $this->goTo($session, 'friend_purpose', ['friend_id' => $friend->id, 'friend_name' => $name],
            __("Mchangie :name:\n1. Ada\n2. Akiba\n0. Rudi", ['name' => $name]));
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
                'label' => __('Akiba ya :name', ['name' => $data['friend_name']]),
            ]);
        }

        if ($input === '1') {
            $friend = Member::query()->findOrFail($data['friend_id']);
            $row = $this->balances->unpaidObligations($friend, 1)->first();

            if (! $row) {
                return $this->backOnly($session, __(':name hana deni la ada.', ['name' => $data['friend_name']]));
            }

            return $this->askAmount($session, [
                'target_type' => 'dues',
                'target_id' => $row['obligation']->id,
                'credited_member_id' => $friend->id,
                'label' => __('Ada :period ya :name', ['period' => $row['obligation']->period, 'name' => $data['friend_name']]),
                'suggested' => $row['outstanding'],
            ]);
        }

        return UssdReply::con(__("Chaguo si sahihi.\n1. Ada\n2. Akiba\n0. Rudi"));
    }

    private function askAmount(UssdSession $session, array $payment): UssdReply
    {
        $prompt = isset($payment['suggested'])
            ? __("Kiasi kinachodaiwa :amount.\nWeka kiasi:", ['amount' => Money::format($payment['suggested'])])
            : __('Weka kiasi (TSh):');

        return $this->goTo($session, 'amount', ['payment' => $payment], $prompt);
    }

    private function amount(UssdSession $session, string $input): UssdReply
    {
        $min = (int) Settings::get('ussd_min_amount');
        $max = (int) Settings::get('ussd_max_amount');

        if (! ctype_digit($input) || (int) $input < $min || (int) $input > $max) {
            return UssdReply::con(__('Kiasi si sahihi. Weka kati ya :min na :max:', ['min' => Money::format($min), 'max' => Money::format($max)]));
        }

        $data = $session->data;
        $data['payment']['amount'] = (int) $input;
        $session->update(['state' => 'confirm', 'data' => $data]);

        return UssdReply::con(__("Thibitisha:\n:label\nKiasi: :amount\n1. Thibitisha\n0. Ghairi", ['label' => Str::limit($data['payment']['label'], 60), 'amount' => Money::format((int) $input)]));
    }

    private function confirm(UssdSession $session, string $input): UssdReply
    {
        if ($input === '0') {
            return UssdReply::end(__('Umeghairi. Hakuna malipo yaliyofanyika.'));
        }

        if ($input !== '1') {
            return UssdReply::con(__("Chaguo si sahihi.\n1. Thibitisha\n0. Ghairi"));
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

        // With a mobile-money push the member approves on their phone; otherwise the treasurer verifies.
        return UssdReply::end($this->gateway->promptsOnPhone($intent)
            ? __("Ombi limepokelewa.\nKumbukumbu: :reference\nSubiri ujumbe wa malipo kwenye simu yako, kisha weka PIN ya pesa. Utapata SMS.", ['reference' => $intent->reference])
            : __("Ombi limepokelewa.\nKumbukumbu: :reference\nMalipo yatathibitishwa na mweka hazina. Utapata SMS.", ['reference' => $intent->reference]));
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
            return $this->backOnly($session, __('Hakuna matangazo kwa sasa.'));
        }

        return $this->backOnly($session, $items->map(fn ($a) => $a->title.': '.str($a->body)->limit(60))->implode("\n"));
    }

    private function backOnly(UssdSession $session, string $text): UssdReply
    {
        return $this->goTo($session, 'back_only', [], $text."\n".__('0. Rudi'));
    }

    private function goTo(UssdSession $session, string $state, array $data, string $text): UssdReply
    {
        $session->update(['state' => $state, 'data' => array_merge($session->data ?? [], $data)]);

        return UssdReply::con($text);
    }
}
