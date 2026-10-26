<?php

namespace App\Http\Controllers\Integrations\AzamPay;

use App\Http\Controllers\Controller;
use App\Integrations\AzamPay\MobileNetwork;
use App\Models\PaymentIntent;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AzamPay's result for a mobile-money push. A successful result is posted as
 * a verified payment; anything unexpected is logged and left for the
 * treasurer, never guessed at. Always answers 200 so AzamPay stops retrying.
 */
class CallbackController extends Controller
{
    public function __invoke(Request $request, VerifiedPaymentService $payments): JsonResponse
    {
        // Field casing differs between AzamPay's docs and SDKs, so match loosely.
        $data = array_change_key_case($request->all(), CASE_LOWER);
        $status = strtolower((string) ($data['transactionstatus'] ?? ''));
        $reference = (string) ($data['utilityref'] ?? '');
        $transaction = (string) ($data['reference'] ?? $data['mnoreference'] ?? '');
        $context = ['utilityref' => $reference, 'status' => $status, 'reference' => $transaction, 'operator' => $data['operator'] ?? null];

        $intent = PaymentIntent::query()->where('reference', $reference)->first();

        if (! $intent) {
            Log::warning('AzamPay callback for unknown payment request', $context);

            return response()->json(['success' => false, 'message' => 'Unknown reference']);
        }

        if ($status !== 'success') {
            // The member cancelled, had too little balance or the prompt timed out.
            if ($intent->status === 'pending') {
                $intent->update(['status' => 'failed']);
            }
            Log::info('AzamPay reported an unsuccessful payment', $context);

            return response()->json(['success' => true]);
        }

        if ($transaction === '') {
            Log::warning('AzamPay success without a transaction reference; left for manual verification', $context);

            return response()->json(['success' => false, 'message' => 'Missing reference']);
        }

        try {
            $payments->post($intent, MobileNetwork::methodForOperator($data['operator'] ?? null), $transaction, (int) round((float) ($data['amount'] ?? 0)), 'provider');
        } catch (RuntimeException $e) {
            // Amount mismatch or already paid by another transaction: a person must look.
            Log::error('AzamPay payment not posted', $context + ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Not posted']);
        }

        return response()->json(['success' => true]);
    }
}
