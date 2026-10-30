<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletionLog;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentRemovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Deleting money records: system administrators only, always with a reason,
 * always logged.
 */
class DeletionController extends Controller
{
    public function index(): View
    {
        return view('admin.deletions', [
            'logs' => DeletionLog::query()->with('user')->where('group_id', $this->groupId())->latest('id')->paginate(20),
        ]);
    }

    public function payment(Request $request, PaymentIntent $payment, PaymentRemovalService $removal): RedirectResponse
    {
        $this->ensureGroup($payment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:190']], [], ['reason' => __('sababu')]);

        $removal->deletePayment($payment->load(['payer', 'creditedMember']), $data['reason'], $request->user());

        return back()->with('status', __('Malipo :reference yamefutwa na kuandikwa kwenye kumbukumbu.', ['reference' => $payment->reference]));
    }

    public function confirmMember(Member $member, PaymentRemovalService $removal): View
    {
        $this->ensureGroup($member);

        return view('admin.delete-member', ['member' => $member, 'summary' => $removal->memberSummary($member)]);
    }

    public function member(Request $request, Member $member, PaymentRemovalService $removal): RedirectResponse
    {
        $this->ensureGroup($member);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:190'],
            'confirm_number' => ['required', 'string'],
        ], [], ['reason' => __('sababu'), 'confirm_number' => __('namba ya uanachama')]);

        // Typing the member number makes deleting the wrong person unlikely.
        if (strcasecmp(trim($data['confirm_number']), $member->member_number) !== 0) {
            return back()->withInput()->withErrors(['confirm_number' => __('Namba hailingani. Andika :number ili kuthibitisha.', ['number' => $member->member_number])]);
        }

        $removal->deleteMemberWithPayments($member, $data['reason'], $request->user());

        return redirect()->route('members.index')->with('status', __('Mwanachama :name (:number) amefutwa pamoja na malipo yake, na imeandikwa kwenye kumbukumbu.', ['name' => $member->full_name, 'number' => $member->member_number]));
    }
}
