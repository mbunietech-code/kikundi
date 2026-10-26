<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::valid($locale), 404);

        $request->session()->put('locale', $locale);

        // A signed-in member's choice also sets the language of their SMS and USSD.
        $request->user('member')?->update(['locale' => $locale]);

        return redirect()->back(fallback: url('/'))->withCookie(Cookie::forever('locale', $locale));
    }
}
