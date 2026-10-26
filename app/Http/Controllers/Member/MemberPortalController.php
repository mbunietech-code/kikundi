<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use App\Services\Membership\MembershipService;
use App\Services\Reminders\ReminderService;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member's own view: status, what they owe, savings, payments and
 * notices. Everything is read for the signed-in member only.
 */
class MemberPortalController extends Controller
{
    public function home(BalanceService $balances, MembershipService $membership, ReminderService $reminders): View
    {
        $member = $this->member();
        $debt = $balances->debtBreakdowns($member->group_id)[$member->id] ?? ['dues' => 0, 'fees' => 0, 'events' => 0];
        $eventOwed = $balances->eventDebts($member->group_id)->where('member_id', $member->id)->pluck('outstanding', 'event_id');

        return view('member.home', [
            'member' => $member,
            'debt' => $debt,
            'savings' => $balances->memberSavings($member),
            'missing' => in_array($member->status, ['pending', 'inactive'], true) ? $membership->missingConditions($member) : [],
            'deadline' => $reminders->duesDeadline(),
            'dues' => $member->obligations()->orderByDesc('period')->take(12)->get()
                ->map(fn ($o) => ['obligation' => $o, 'paid' => $balances->allocatedTo('dues', $o->id)]),
            'events' => ContributionEvent::query()->where('group_id', $member->group_id)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->latest()->get()
                ->map(fn ($e) => ['event' => $e, 'owed' => $eventOwed[$e->id] ?? null]),
            'payments' => PaymentIntent::query()
                ->where(fn ($q) => $q->where('credited_member_id', $member->id)->orWhere('payer_member_id', $member->id))
                ->whereIn('status', ['pending', 'succeeded'])
                ->with(['payer', 'creditedMember'])->latest()->take(10)->get(),
            'announcements' => Announcement::query()->where('group_id', $member->group_id)->where('status', 'published')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('published_at')->take(3)->get(),
        ]);
    }

    /** The member's own statement for a year, as PDF. */
    public function statement(Request $request, ReportService $reports, ReportExporter $exporter): Response
    {
        $member = $this->member();
        $year = (int) $request->query('year', now()->year);
        $year = $year >= 2000 && $year <= now()->year ? $year : now()->year;

        return $exporter->pdf($reports->member($member->group, ReportPeriod::year($year), $member));
    }

    public function photo(): Response
    {
        $member = $this->member();
        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path);
    }

    public function editPin(): View
    {
        return view('member.pin', ['member' => $this->member()]);
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $member = $this->member();
        $data = $request->validate([
            'current_pin' => ['required', 'digits:4'],
            'pin' => ['required', 'digits:4', 'confirmed', 'different:current_pin'],
        ], ['pin.different' => __('PIN mpya isiwe sawa na ya zamani.')], ['current_pin' => __('PIN ya sasa'), 'pin' => __('PIN mpya')]);

        if (! Hash::check($data['current_pin'], $member->pin_hash)) {
            return back()->withErrors(['current_pin' => __('PIN ya sasa si sahihi.')]);
        }

        $member->update(['pin_hash' => Hash::make($data['pin'])]);

        return redirect()->route('member.home')->with('status', __('PIN imebadilishwa. Itumie pia kwenye USSD.'));
    }

    private function member(): Member
    {
        return Auth::guard('member')->user();
    }
}
