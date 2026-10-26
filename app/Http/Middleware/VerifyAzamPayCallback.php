<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The callback URL registered with AzamPay carries a secret token, and the
 * body carries AzamPay's callback password when one is configured.
 */
class VerifyAzamPayCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('azampay.callback_token');

        if ($expected === '' || ! hash_equals($expected, (string) $request->route('token'))) {
            abort(404);
        }

        $password = (string) config('azampay.callback_password');

        if ($password !== '' && ! hash_equals($password, (string) $request->input('password'))) {
            abort(401);
        }

        return $next($request);
    }
}
