<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Phone number and USSD PIN sign-in. Wrong PINs count towards the same
 * lock as USSD, so the portal cannot be used to guess a PIN.
 */
class MemberLoginController extends Controller
{
    public function show(): View
    {
        return view('member.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
        ], [], ['phone' => __('namba ya simu'), 'pin' => 'PIN']);

        $member = Member::query()
            ->where('group_id', config('beem.ussd_group_id'))
            ->where('phone', Phone::normalise($data['phone']))
            ->where('status', '!=', 'departed')
            ->first();

        $fail = fn (string $message) => back()->withErrors(['phone' => $message])->onlyInput('phone');

        if (! $member || ! $member->pin_hash) {
            return $fail(__('Namba au PIN si sahihi. Kama hujawekewa PIN, wasiliana na katibu.'));
        }

        if ($member->pin_locked_until?->isFuture()) {
            return $fail(__('PIN imefungwa kwa muda baada ya majaribio mengi. Jaribu tena baada ya :time.', ['time' => $member->pin_locked_until->diffForHumans(syntax: true)]));
        }

        if (! Hash::check($data['pin'], $member->pin_hash)) {
            $attempts = $member->pin_failed_attempts + 1;
            $max = (int) config('beem.ussd_pin_max_attempts');

            $member->update($attempts >= $max
                ? ['pin_failed_attempts' => 0, 'pin_locked_until' => now()->addMinutes(config('beem.ussd_pin_lock_minutes'))]
                : ['pin_failed_attempts' => $attempts]);

            return $fail($attempts >= $max ? __('PIN si sahihi mara nyingi. Imefungwa kwa muda.') : __('Namba au PIN si sahihi.'));
        }

        $member->update(['pin_failed_attempts' => 0, 'pin_locked_until' => null]);
        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        return redirect()->intended(route('member.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Only the member guard; an officer signed in on the same browser stays in.
        Auth::guard('member')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('member.login');
    }
}
