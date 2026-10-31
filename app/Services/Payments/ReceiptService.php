<?php

namespace App\Services\Payments;

use App\Http\Controllers\Officer\PaymentController;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\Response;

/**
 * A printable receipt for one recorded payment, for the treasurer to hand
 * over and for the member to download from the portal.
 */
class ReceiptService
{
    public function __construct(private VerifiedPaymentService $payments) {}

    public function pdf(Payment $payment): Response
    {
        $payment->loadMissing('intent.payer', 'intent.creditedMember');
        $intent = $payment->intent;

        $options = new DompdfOptions;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setChroot(resource_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('payments.receipt', [
            'payment' => $payment,
            'intent' => $intent,
            'purpose' => $intent ? $this->payments->purpose($intent, app()->getLocale()) : '',
            'method' => __((PaymentController::METHODS + PaymentController::PROVIDER_METHODS)[$payment->provider] ?? ucfirst($payment->provider)),
            'officer' => $payment->verified_by ? User::query()->whereKey($payment->verified_by)->value('name') : null,
            'systemName' => Settings::systemName(),
        ])->render());
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$payment->receipt_number.'.pdf"',
        ]);
    }
}
