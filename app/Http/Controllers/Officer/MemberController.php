<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use App\Services\Membership\MembershipService;
use App\Support\Locale;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public const STATUSES = ['active' => 'Hai', 'inactive' => 'Sio mwanachama hai', 'pending' => 'Inasubiri kiingilio', 'suspended' => 'Amesimamishwa', 'departed' => 'Ameondoka'];

    /** Statuses an officer may set by hand; the rest follow payments. */
    public const MANUAL_STATUSES = ['suspended' => 'Amesimamishwa', 'departed' => 'Ameondoka'];

    public function index(Request $request): View
    {
        // Only active members are listed unless another status is asked for.
        $status = $request->query('status', 'active');
        $status = $status === 'all' || array_key_exists($status, self::STATUSES) ? $status : 'active';

        $members = Member::query()
            ->where('group_id', $this->groupId())
            ->when($request->string('q')->trim()->value(), function ($query, $q) {
                $digits = preg_replace('/\D+/', '', $q);

                $query->where(fn ($w) => $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('member_number', 'like', "%{$q}%")
                    ->when(strlen($digits) >= 3, fn ($p) => $p->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%')));
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->withCount('dependants')
            ->orderBy('member_number')
            ->paginate(15)
            ->withQueryString();

        $counts = Member::query()->where('group_id', $this->groupId())->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('officer.members.index', ['members' => $members, 'statuses' => self::STATUSES, 'status' => $status, 'counts' => $counts]);
    }

    public function create(): View
    {
        return view('officer.members.form', [
            'member' => new Member(['joined_at' => now(), 'member_number' => $this->nextMemberNumber()]),
            'statuses' => self::MANUAL_STATUSES,
            'entryFee' => (int) Settings::get('entry_fee'),
        ]);
    }

    public function store(Request $request, MembershipService $membership): RedirectResponse
    {
        if (blank($request->input('member_number'))) {
            $request->merge(['member_number' => $this->nextMemberNumber()]);
        }

        $member = Member::query()->create($this->validated($request) + ['group_id' => $this->groupId()]);
        $this->storePhoto($request, $member);
        $membership->register($member, $request->boolean('entry_fee_paid'), $request->user()->id);

        return redirect()->route('members.show', $member)->with('status', $member->status === 'pending'
            ? __('Mwanachama :name (:number) amesajiliwa. Atakuwa hai akilipa kiingilio.', ['name' => $member->full_name, 'number' => $member->member_number])
            : __('Mwanachama :name (:number) amesajiliwa.', ['name' => $member->full_name, 'number' => $member->member_number]));
    }

    public function show(Member $member, BalanceService $balances, MembershipService $membership): View
    {
        $this->ensureGroup($member);

        $obligations = $member->obligations()->orderByDesc('period')->get()
            ->map(fn ($o) => ['obligation' => $o, 'paid' => $balances->allocatedTo('dues', $o->id)]);

        return view('officer.members.show', [
            'member' => $member->load(['dependants', 'statusChanges' => fn ($q) => $q->with('changedBy')->latest('id')]),
            'fees' => $member->fees()->latest('id')->get()->map(fn ($fee) => ['fee' => $fee, 'paid' => $balances->allocatedTo('membership_fee', $fee->id)]),
            'missing' => in_array($member->status, ['pending', 'inactive'], true) ? $membership->missingConditions($member) : [],
            'unpaidMonths' => $membership->unpaidMonths($member),
            'graceMonths' => (int) Settings::get('grace_months'),
            'obligations' => $obligations,
            'outstanding' => $obligations->sum(fn ($row) => max(0, $row['obligation']->amount_due - $row['paid'])),
            'savings' => $balances->memberSavings($member),
            'canDelete' => $membership->canDelete($member),
            'payments' => PaymentIntent::query()
                ->where(fn ($q) => $q->where('credited_member_id', $member->id)->orWhere('payer_member_id', $member->id))
                ->with(['payer', 'creditedMember'])
                ->latest()->take(10)->get(),
        ]);
    }

    public function edit(Member $member): View
    {
        $this->ensureGroup($member);

        return view('officer.members.form', ['member' => $member, 'statuses' => self::MANUAL_STATUSES]);
    }

    public function update(Request $request, Member $member, MembershipService $membership): RedirectResponse
    {
        $this->ensureGroup($member);
        $member->update($this->validated($request, $member));
        $this->storePhoto($request, $member);

        // Officers may suspend or record a departure, and lift either. Status
        // changes caused by paying or not paying happen on their own.
        $action = (string) $request->input('status_action');

        if (array_key_exists($action, self::MANUAL_STATUSES) && $member->status !== $action) {
            $membership->changeStatus($member, $action, $request->input('status_reason') ?: self::MANUAL_STATUSES[$action], $request->user()->id);
        } elseif ($action === 'active' && array_key_exists($member->status, self::MANUAL_STATUSES)) {
            $membership->changeStatus($member, 'active', $request->input('status_reason') ?: 'Amerejeshwa na kiongozi', $request->user()->id);
        }

        return redirect()->route('members.show', $member)->with('status', __('Taarifa za mwanachama zimehifadhiwa.'));
    }

    public function destroy(Member $member, MembershipService $membership): RedirectResponse
    {
        $this->ensureGroup($member);

        try {
            $membership->delete($member);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('members.index')->with('status', __('Mwanachama :name (:number) amefutwa.', ['name' => $member->full_name, 'number' => $member->member_number]));
    }

    /** Photos stay on the private disk and are only shown to officers. */
    public function photo(Member $member): StreamedResponse
    {
        $this->ensureGroup($member);
        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path);
    }

    public function updatePin(Request $request, Member $member): RedirectResponse
    {
        $this->ensureGroup($member);
        $data = $request->validate(['pin' => ['required', 'digits:4', 'confirmed']], [], ['pin' => 'PIN']);

        $member->update(['pin_hash' => Hash::make($data['pin']), 'pin_failed_attempts' => 0, 'pin_locked_until' => null]);

        return back()->with('status', __('PIN ya USSD imewekwa. Mjulishe mwanachama kwa njia salama.'));
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        $request->merge([
            'member_number' => strtoupper(trim((string) $request->input('member_number'))),
            'phone' => Phone::normalise((string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'member_number' => ['required', 'string', 'max:20', Rule::unique('members')->where('group_id', $this->groupId())->ignore($member?->id)],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^255[67]\d{8}$/'],
            'address' => ['nullable', 'string', 'max:190'],
            'joined_at' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'locale' => ['nullable', Rule::in(array_keys(Locale::SUPPORTED))],
            'status_action' => ['nullable', Rule::in(['active', ...array_keys(self::MANUAL_STATUSES)])],
            'status_reason' => ['nullable', 'string', 'max:190'],
        ], ['phone.regex' => __('Weka namba sahihi ya simu, mfano 0712345678.')], [
            'member_number' => __('namba ya uanachama'), 'full_name' => __('jina kamili'), 'phone' => __('simu'), 'joined_at' => __('tarehe ya kujiunga'), 'photo' => __('picha'), 'locale' => __('lugha'),
        ]);

        // Photo and status are saved separately, the status with its history.
        return array_diff_key($data, array_flip(['photo', 'status_action', 'status_reason']));
    }

    private function storePhoto(Request $request, Member $member): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $old = $member->photo_path;
        $member->update(['photo_path' => $request->file('photo')->store('member-photos', 'local')]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    /** Next free number in the group's M001 style. */
    private function nextMemberNumber(): string
    {
        $highest = Member::query()->where('group_id', $this->groupId())->where('member_number', 'like', 'M%')->pluck('member_number')
            ->map(fn ($n) => (int) preg_replace('/\D/', '', $n))->max() ?? 0;

        return 'M'.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }
}
