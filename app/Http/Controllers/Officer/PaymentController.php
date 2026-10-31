<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\MembershipFee;
use App\Models\Obligation;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use App\Services\Payments\PaymentIntentService;
use App\Services\Payments\ReceiptService;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public const TARGETS = ['dues' => 'Ada', 'savings' => 'Akiba', 'event' => 'Mchango', 'membership_fee' => 'Kiingilio'];

    public const METHODS = ['cash' => 'Taslimu', 'mpesa' => 'M-Pesa', 'tigopesa' => 'Mixx by Yas', 'airtelmoney' => 'Airtel Money', 'halopesa' => 'HaloPesa', 'bank' => 'Benki'];

    /** Methods recorded only by a provider, never typed in at the office. */
    public const PROVIDER_METHODS = ['clickpesa' => 'ClickPesa', 'azampay' => 'AzamPay', 'azampesa' => 'AzamPesa'];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'succeeded', 'failed', 'expired'], true) ? $request->query('status') : 'pending';
        $groupId = $this->groupId();

        $payments = PaymentIntent::query()
            ->with(['payer', 'creditedMember', 'payment'])
            ->where('group_id', $groupId)
            ->where('status', $status)
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")
                ->orWhereHas('payment', fn ($p) => $p->where('receipt_number', 'like', "%{$term}%")->orWhere('provider_transaction_id', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = PaymentIntent::query()->where('group_id', $groupId)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('officer.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'counts' => $counts,
            'labels' => $this->targetLabels($payments->getCollection()),
        ]);
    }

    public function create(BalanceService $balances): View
    {
        $groupId = $this->groupId();

        return view('officer.payments.create', [
            // Members waiting for an entry fee or coming back must be able to pay too.
            'members' => Member::query()->where('group_id', $groupId)->where('status', '!=', 'departed')->orderBy('full_name')->get()
                ->mapWithKeys(fn ($m) => [$m->id => "{$m->full_name} ({$m->member_number})".($m->isActive() ? '' : ' · '.__(MemberController::STATUSES[$m->status] ?? $m->status))])->all(),
            'obligations' => Obligation::query()->with('member')->where('group_id', $groupId)->orderBy('period')->get()
                ->filter(fn ($o) => $balances->outstandingFor($o) > 0)
                ->mapWithKeys(fn ($o) => [$o->id => "{$o->member->full_name}, {$o->period} (".__('deni').' '.number_format($balances->outstandingFor($o)).')'])->all(),
            'events' => ContributionEvent::query()->where('group_id', $groupId)->where('status', 'active')->pluck('title', 'id')->all(),
            'fees' => MembershipFee::query()->with('member')->where('group_id', $groupId)->get()
                ->filter(fn ($f) => $balances->feeOutstanding($f) > 0)
                ->mapWithKeys(fn ($f) => [$f->id => "{$f->member->full_name}, ".__($f->label()).' ('.__('deni').' '.number_format($balances->feeOutstanding($f)).')'])->all(),
            'methods' => self::METHODS,
        ]);
    }

    /** Record money the treasurer has already confirmed in official records. */
    public function store(Request $request, PaymentIntentService $intents, VerifiedPaymentService $verified): RedirectResponse
    {
        $groupId = $this->groupId();

        $data = $request->validate([
            'credited_member_id' => ['required', Rule::exists('members', 'id')->where('group_id', $groupId)],
            'target_type' => ['required', Rule::in(array_keys(self::TARGETS))],
            'obligation_id' => ['required_if:target_type,dues', 'nullable', Rule::exists('obligations', 'id')->where('group_id', $groupId)],
            'event_id' => ['required_if:target_type,event', 'nullable', Rule::exists('contribution_events', 'id')->where('group_id', $groupId)],
            'fee_id' => ['required_if:target_type,membership_fee', 'nullable', Rule::exists('membership_fees', 'id')->where('group_id', $groupId)],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_keys(self::METHODS))],
            // Cash has no network transaction; the receipt number is the record.
            'transaction_id' => ['required_unless:method,cash', 'nullable', 'string', 'max:60'],
            'form_token' => ['required', 'string', 'max:64'],
        ], [], ['credited_member_id' => __('mwanachama'), 'target_type' => __('aina'), 'obligation_id' => __('mwezi wa ada'), 'event_id' => __('mchango'), 'fee_id' => __('kiingilio'), 'amount' => __('kiasi'), 'method' => __('njia'), 'transaction_id' => __('namba ya muamala')]);

        $member = Member::query()->findOrFail($data['credited_member_id']);
        $targetId = match ($data['target_type']) {
            'dues' => (int) $data['obligation_id'],
            'event' => (int) $data['event_id'],
            'membership_fee' => (int) $data['fee_id'],
            default => null,
        };

        if ($data['target_type'] === 'dues' && Obligation::query()->find($targetId)?->member_id !== $member->id) {
            return back()->withInput()->withErrors(['obligation_id' => __('Mwezi huu wa ada si wa mwanachama huyu.')]);
        }

        if ($data['target_type'] === 'membership_fee' && MembershipFee::query()->find($targetId)?->member_id !== $member->id) {
            return back()->withInput()->withErrors(['fee_id' => __('Kiingilio hiki si cha mwanachama huyu.')]);
        }

        $transactionId = filled($data['transaction_id'] ?? null) ? trim($data['transaction_id']) : null;

        // A network or bank transaction can be recorded only once; say so instead of silently keeping the old one.
        if ($transactionId && ($used = Payment::query()->where('provider', $data['method'])->where('provider_transaction_id', $transactionId)->first())) {
            return back()->withInput()->withErrors(['transaction_id' => __('Namba hii ya muamala imesharekodiwa kwenye risiti :receipt.', ['receipt' => $used->receipt_number])]);
        }

        try {
            // The form token makes a double click record the payment once, not twice.
            $intent = $intents->create($member, $member, $data['target_type'], $targetId, (int) $data['amount'],
                'office:'.$data['form_token'], 'office', $member->phone);
            $payment = $verified->post($intent, $data['method'], $transactionId ?? 'CASH-'.$intent->reference, (int) $data['amount'], 'manual', $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', __('Malipo hayakurekodiwa: :reason', ['reason' => $e->getMessage()]));
        }

        return redirect()->route('payments.index', ['status' => 'succeeded'])
            ->with('status', __('Malipo yamerekodiwa. Risiti :receipt imetumwa kwa SMS.', ['receipt' => $payment->receipt_number]))
            ->with('receipt', $payment->id);
    }

    public function verify(Request $request, PaymentIntent $payment, VerifiedPaymentService $verified): RedirectResponse
    {
        $this->ensureGroup($payment);

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(self::METHODS))],
            'transaction_id' => ['required', 'string', 'max:60'],
        ], [], ['transaction_id' => __('namba ya muamala')]);

        try {
            $verified->post($payment, $data['method'], $data['transaction_id'], (int) $payment->amount, 'manual', $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', __('Haikuthibitishwa: :reason', ['reason' => $e->getMessage()]));
        }

        return back()->with('status', __('Malipo :reference yamethibitishwa.', ['reference' => $payment->reference]));
    }

    /** Printable receipt for a recorded payment. */
    public function receipt(Payment $payment, ReceiptService $receipts): Response
    {
        $this->ensureGroup($payment);

        return $receipts->pdf($payment);
    }

    public function reject(PaymentIntent $payment): RedirectResponse
    {
        $this->ensureGroup($payment);
        abort_unless($payment->status === 'pending', 409);

        $payment->update(['status' => 'failed']);

        return back()->with('status', __('Ombi :reference limekataliwa. Hakuna salio lililobadilika.', ['reference' => $payment->reference]));
    }

    private function targetLabels($payments): array
    {
        $events = ContributionEvent::query()->whereIn('id', $payments->where('target_type', 'event')->pluck('target_id'))->pluck('title', 'id');
        $periods = Obligation::query()->whereIn('id', $payments->where('target_type', 'dues')->pluck('target_id'))->pluck('period', 'id');

        return $payments->mapWithKeys(fn ($p) => [$p->id => match ($p->target_type) {
            'dues' => __('Ada').' '.($periods[$p->target_id] ?? ''),
            'event' => Str::limit($events[$p->target_id] ?? __('Mchango'), 28),
            'membership_fee' => __('Kiingilio'),
            default => __('Akiba'),
        }])->all();
    }
}
