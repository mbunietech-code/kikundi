<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => Settings::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:40'],
            'support_contact' => ['nullable', 'string', 'max:60'],
            'ussd_min_amount' => ['required', 'integer', 'min:1'],
            'ussd_max_amount' => ['required', 'integer', 'gt:ussd_min_amount'],
            'grace_months' => ['sometimes', 'required', 'integer', 'min:1', 'max:24'],
            'entry_fee' => ['sometimes', 'required', 'integer', 'min:0'],
            'reinstatement_fee' => ['sometimes', 'required', 'integer', 'min:0'],
            'reinstate_requires_arrears' => ['sometimes', 'required', 'in:0,1'],
            'dues_due_day' => ['sometimes', 'required', 'integer', 'min:1', 'max:28'],
            'reminder_days_before' => ['sometimes', 'required', 'integer', 'min:0', 'max:14'],
            'reminders_enabled' => ['sometimes', 'required', 'in:0,1'],
            'backup_keep_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'default_locale' => ['sometimes', 'required', 'in:'.implode(',', array_keys(Locale::SUPPORTED))],
        ], [], ['system_name' => __('jina la mfumo'), 'grace_months' => __('miezi ya neema'), 'entry_fee' => __('kiingilio'), 'reinstatement_fee' => __('kiingilio cha kurejea'), 'dues_due_day' => __('siku ya mwisho'), 'reminder_days_before' => __('siku za ukumbusho'), 'default_locale' => __('lugha')]);

        Settings::set($data, $request->user()->id);

        return back()->with('status', __('Mipangilio imehifadhiwa.'));
    }
}
