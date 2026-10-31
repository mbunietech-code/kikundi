<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Membership\MembershipService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MembershipController extends Controller
{
    /** Run the daily status check now, e.g. on WAMP without a scheduler. */
    public function enforce(MembershipService $membership): RedirectResponse
    {
        $count = $membership->enforce($this->groupId());

        return back()->with('status', $count
            ? __('Wanachama :count wamekuwa si hai kwa kutolipa ada, na wamejulishwa kwa SMS.', ['count' => $count])
            : __('Hakuna mwanachama aliyebadilika hali.'));
    }

    public function toggle(Request $request, Member $member, MembershipService $membership): RedirectResponse
    {
        $this->ensureGroup($member);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:200']]);

        try {
            $membership->setEnabled($member, (bool) $data['enabled'], $data['reason'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $data['enabled']
            ? __(':name amewashwa na sasa ni mwanachama hai.', ['name' => $member->full_name])
            : __(':name amezimwa. Hataweza kutumia USSD wala kuchangia hadi awashwe.', ['name' => $member->full_name]));
    }

    public function reinstate(Request $request, Member $member, MembershipService $membership): RedirectResponse
    {
        $this->ensureGroup($member);

        try {
            $fee = $membership->startReinstatement($member, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $member->refresh();

        return back()->with('status', $member->isActive()
            ? __(':name amerejeshwa kuwa mwanachama hai.', ['name' => $member->full_name])
            : __('Kurejea kumeanzishwa. Kiingilio cha :amount kinasubiri kulipwa.', ['amount' => Money::format($fee->amount_due)]));
    }
}
