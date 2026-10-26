<?php

namespace App\Http\Controllers\Integrations\Beem;

use App\Http\Controllers\Controller;
use App\Integrations\Beem\BeemUssdAdapter;
use App\Models\Member;
use App\Models\UssdSession;
use App\Services\Ussd\UssdMenuService;
use App\Services\Ussd\UssdReply;
use App\Support\Locale;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UssdController extends Controller
{
    public function __invoke(Request $request, BeemUssdAdapter $adapter, UssdMenuService $menu): JsonResponse
    {
        $data = $request->validate($adapter->rules());

        $session = UssdSession::query()->firstOrCreate(
            ['session_id' => (string) $data['session_id']],
            ['msisdn' => (string) $data['msisdn'], 'operator' => $data['operator'] ?? null, 'state' => 'start', 'step' => 0, 'authenticated' => false],
        );

        // A different phone reusing a session id must never inherit its state.
        if ($session->msisdn !== (string) $data['msisdn']) {
            return response()->json($adapter->format($data, UssdReply::end(__('Hitilafu ya kipindi. Piga tena.')), 0));
        }

        // Every screen speaks the member's language; unknown numbers get the default.
        $member = $session->member ?? Member::query()
            ->where('group_id', config('beem.ussd_group_id'))
            ->where('phone', Phone::normalise($session->msisdn))
            ->first();
        app()->setLocale($member?->preferredLocale() ?? Locale::default());

        $session->increment('step');

        if ($data['command'] === 'terminate') {
            $session->update(['state' => 'ended', 'ended_at' => now()]);

            return response()->json($adapter->format($data, UssdReply::end(__('Asante.')), $session->step));
        }

        if ($session->state === 'ended') {
            return response()->json($adapter->format($data, UssdReply::end(__('Kipindi kimeisha. Tafadhali piga tena.')), $session->step));
        }

        try {
            $reply = $menu->handle($session, $adapter->input($data));
        } catch (Throwable $e) {
            Log::error('USSD menu failure', ['session_id' => $session->session_id, 'error' => $e->getMessage()]);
            $session->update(['state' => 'ended', 'ended_at' => now()]);
            $reply = UssdReply::end(__('Samahani, kuna hitilafu. Jaribu tena baadaye.'));
        }

        return response()->json($adapter->format($data, $reply, $session->step));
    }
}
