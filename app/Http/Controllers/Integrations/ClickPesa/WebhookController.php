<?php

namespace App\Http\Controllers\Integrations\ClickPesa;

use App\Http\Controllers\Controller;
use App\Jobs\SettleClickPesaPayment;
use App\Models\PaymentIntent;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ClickPesa's notice that an order changed. The body is only a hint: the
 * status is read back from ClickPesa before anything is posted. Always
 * answers 200 so ClickPesa stops retrying.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $expected = (string) Settings::get('clickpesa_webhook_token');
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        $reference = (string) ($request->input('data.orderReference') ?? $request->input('orderReference') ?? '');
        $intent = $reference !== '' ? PaymentIntent::query()->where('reference', $reference)->first() : null;

        if (! $intent) {
            Log::warning('ClickPesa webhook for unknown order', ['orderReference' => $reference, 'event' => $request->input('event')]);

            return response()->json(['success' => false]);
        }

        SettleClickPesaPayment::dispatch($intent->id);

        return response()->json(['success' => true]);
    }
}
