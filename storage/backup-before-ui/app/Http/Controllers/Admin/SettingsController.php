<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);

        Settings::set($data, $request->user()->id);

        return back()->with('status', 'Mipangilio imehifadhiwa.');
    }
}
