<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Integrations\Beem\UssdController;
use App\Integrations\Beem\BeemUssdAdapter;
use App\Models\Member;
use App\Services\Ussd\UssdMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phone-style page for trying the USSD menu without Beem. Registered only in
 * local and testing environments; it skips the callback token on purpose.
 */
class UssdSimulatorController extends Controller
{
    public function show(): View
    {
        $members = Member::query()
            ->where('group_id', config('beem.ussd_group_id'))
            ->orderBy('member_number')
            ->get(['member_number', 'full_name', 'phone', 'pin_hash']);

        return view('dev.ussd', ['members' => $members]);
    }

    public function send(Request $request, BeemUssdAdapter $adapter, UssdMenuService $menu): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'in:initiate,continue,terminate'],
            'msisdn' => ['required', 'string', 'max:20'],
            'session_id' => ['required', 'string', 'max:100'],
            'response' => ['nullable', 'string', 'max:160'],
        ]);

        // Same body Beem would post, handled by the real callback controller.
        $callback = Request::create('/', 'POST', [
            'command' => $data['command'],
            'msisdn' => $data['msisdn'],
            'operator' => 'simulator',
            'session_id' => $data['session_id'],
            'payload' => ['request_id' => 0, 'response' => $data['response'] ?? '0'],
        ]);

        return app(UssdController::class)($callback, $adapter, $menu);
    }
}
