<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use App\Services\Payments\PaymentRemovalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContributionEventController extends Controller
{
    public const TYPES = ContributionEvent::TYPES;

    public const STATUSES = ['draft' => 'Rasimu', 'active' => 'Hai', 'closed' => 'Imefungwa', 'cancelled' => 'Imeghairiwa'];

    public function index(Request $request, BalanceService $balances): View
    {
        $events = ContributionEvent::query()
            ->with('beneficiary')
            ->where('group_id', $this->groupId())
            ->when($request->string('type')->value(), fn ($q, $type) => $q->where('event_type', $type))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('officer.events.index', [
            'events' => $events,
            'totals' => $events->getCollection()->mapWithKeys(fn ($e) => [$e->id => $balances->allocatedTo('event', $e->id)]),
            'types' => self::TYPES,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-events');

        return view('officer.events.form', [
            'event' => new ContributionEvent(['event_type' => request('type', 'illness'), 'status' => 'active', 'starts_at' => now()]),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'members' => $this->memberOptions(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-events');
        $event = ContributionEvent::query()->create($this->validated($request, null) + ['group_id' => $this->groupId()]);

        return redirect()->route('events.show', $event)->with('status', __('Mchango umeundwa. Wanachama wanaweza kuchangia kupitia USSD ukiwa "Hai".'));
    }

    public function show(ContributionEvent $event): View
    {
        $this->ensureGroup($event);

        $contributions = Allocation::query()
            ->where('group_id', $event->group_id)->where('target_type', 'event')->where('target_id', $event->id)
            ->latest()->get();

        return view('officer.events.show', [
            'event' => $event->load('beneficiary'),
            'contributions' => $contributions,
            'total' => (int) $contributions->sum('amount'),
            'memberNames' => Member::query()->where('group_id', $event->group_id)->pluck('full_name', 'id'),
            'expected' => $this->expectedFrom($event, $contributions),
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
        ]);
    }

    public function edit(ContributionEvent $event): View
    {
        Gate::authorize('manage-events');
        $this->ensureGroup($event);

        return view('officer.events.form', ['event' => $event, 'types' => self::TYPES, 'statuses' => self::STATUSES, 'members' => $this->memberOptions($event)]);
    }

    public function update(Request $request, ContributionEvent $event): RedirectResponse
    {
        Gate::authorize('manage-events');
        $this->ensureGroup($event);
        $event->update($this->validated($request, $event));

        return redirect()->route('events.show', $event)->with('status', __('Mchango umesasishwa.'));
    }

    /**
     * An event with no money in it can be deleted by any events officer.
     * One with contributions only by the administrator, with a reason.
     */
    public function destroy(Request $request, ContributionEvent $event, PaymentRemovalService $removal): RedirectResponse
    {
        Gate::authorize('manage-events');
        $this->ensureGroup($event);

        $hasMoney = PaymentIntent::query()->where('target_type', 'event')->where('target_id', $event->id)->where('status', 'succeeded')->exists();

        if ($hasMoney) {
            Gate::authorize('manage-system');
        }

        $data = $request->validate(['reason' => [$hasMoney ? 'required' : 'nullable', 'string', 'min:5', 'max:300']], [], ['reason' => __('sababu')]);

        $removal->deleteEvent($event, $data['reason'] ?? __('Mchango usio na malipo'), $request->user());

        return redirect()->route('events.index')->with('status', __('Mchango ":title" umefutwa.', ['title' => $event->title]));
    }

    /**
     * Per-member progress when the event asks a fixed amount from each
     * active member. Empty when members give what they can.
     */
    private function expectedFrom(ContributionEvent $event, $contributions): array
    {
        if (! $event->amount_per_member) {
            return [];
        }

        $paid = $contributions->groupBy('credited_member_id')->map->sum('amount');

        return Member::query()->where('group_id', $event->group_id)->where('status', 'active')
            ->whereKeyNot($event->beneficiary_member_id ?? 0)->orderBy('member_number')->get()
            ->map(fn (Member $m) => ['member' => $m, 'paid' => (int) ($paid[$m->id] ?? 0), 'balance' => max(0, $event->amount_per_member - (int) ($paid[$m->id] ?? 0))])
            ->sortByDesc('balance')->values()->all();
    }

    /** Only active members have the right to be helped; an existing choice is kept. */
    private function memberOptions(?ContributionEvent $event): array
    {
        return Member::query()->where('group_id', $this->groupId())
            ->where(fn ($q) => $q->where('status', 'active')->when($event?->beneficiary_member_id, fn ($w, $id) => $w->orWhere('id', $id)))
            ->orderBy('full_name')->get()
            ->mapWithKeys(fn ($m) => [$m->id => "{$m->full_name} ({$m->member_number})"])->all();
    }

    private function validated(Request $request, ?ContributionEvent $event): array
    {
        $beneficiary = Rule::exists('members', 'id')->where('group_id', $this->groupId());

        if ((int) $request->input('beneficiary_member_id') !== (int) $event?->beneficiary_member_id) {
            $beneficiary->where('status', 'active');
        }

        $data = $request->validate([
            'event_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'title' => ['required', 'string', 'max:60'],
            'beneficiary_member_id' => ['nullable', $beneficiary],
            'description' => ['nullable', 'string', 'max:500'],
            'amount_per_member' => ['nullable', 'integer', 'min:1'],
            'target_amount' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ], [], ['event_type' => __('aina'), 'title' => __('jina la mchango'), 'beneficiary_member_id' => __('mnufaika'), 'amount_per_member' => __('kiasi kwa kila mwanachama'),
            'target_amount' => __('lengo'), 'starts_at' => __('tarehe ya kuanza'), 'ends_at' => __('tarehe ya mwisho')]);

        // The last day still counts: contributions close at its end.
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->startOfDay();
        $data['ends_at'] = $data['ends_at'] ? CarbonImmutable::parse($data['ends_at'])->endOfDay() : null;

        return $data;
    }
}
