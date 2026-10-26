<?php

use App\Http\Controllers\Integrations\Beem\UssdController;
use App\Http\Middleware\VerifyBeemRequest;
use Illuminate\Support\Facades\Route;

// Provider callbacks: no browser session or CSRF, authenticated by VerifyBeemRequest.
Route::post('/integrations/beem/ussd/{token}', UssdController::class)
    ->middleware([VerifyBeemRequest::class, 'throttle:120,1'])
    ->name('beem.ussd');
