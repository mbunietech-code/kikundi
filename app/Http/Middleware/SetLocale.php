<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language for this request: the visitor's choice (session, then cookie),
 * else a signed-in member's saved language, else the group default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = collect([
            $request->session()->get('locale'),
            $request->cookie('locale'),
            $request->user('member')?->locale,
        ])->first(fn ($candidate) => Locale::valid($candidate)) ?? Locale::default();

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
