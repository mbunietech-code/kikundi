<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beem documents no signature on USSD callbacks, so the callback URL carries
 * a secret token and may be limited to Beem's IP addresses.
 */
class VerifyBeemRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('beem.ussd_token');
        $given = (string) $request->route('token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(404);
        }

        $allowed = config('beem.ussd_allowed_ips');

        if ($allowed && ! in_array($request->ip(), $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
