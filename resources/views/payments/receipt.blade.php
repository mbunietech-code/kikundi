@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Risiti') }} {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #0f172a; }
        .brand { color: #047857; font-weight: bold; font-size: 14px; }
        h1 { font-size: 18px; margin: 10px 0 2px; }
        .muted { color: #64748b; }
        .amount { margin: 16px 0; padding: 12px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; text-align: center; }
        .amount .value { font-size: 22px; font-weight: bold; color: #065f46; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        td.label { color: #64748b; width: 40%; }
        .footer { margin-top: 22px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">{{ $systemName }}</div>
    <h1>{{ __('Risiti ya malipo') }}</h1>
    <div class="muted">{{ __('Namba ya risiti') }}: <strong style="color:#0f172a">{{ $payment->receipt_number }}</strong></div>

    <div class="amount">
        <div class="muted">{{ __('Kiasi kilichopokelewa') }}</div>
        <div class="value">{{ Money::format($payment->amount, $payment->currency) }}</div>
    </div>

    <table>
        <tr><td class="label">{{ __('Tarehe') }}</td><td>{{ $payment->verified_at?->format('d/m/Y H:i') }}</td></tr>
        @if ($intent?->creditedMember)
            <tr><td class="label">{{ __('Mwanachama') }}</td><td>{{ $intent->creditedMember->full_name }} ({{ $intent->creditedMember->member_number }})</td></tr>
        @endif
        @if ($intent?->payer && $intent->payer_member_id !== $intent->credited_member_id)
            <tr><td class="label">{{ __('Amelipa') }}</td><td>{{ $intent->payer->full_name }}</td></tr>
        @endif
        <tr><td class="label">{{ __('Malipo ya') }}</td><td>{{ ucfirst($purpose) }}</td></tr>
        <tr><td class="label">{{ __('Njia ya malipo') }}</td><td>{{ $method }}</td></tr>
        @unless (str_starts_with($payment->provider_transaction_id, 'CASH-'))
            <tr><td class="label">{{ __('Namba ya muamala') }}</td><td>{{ $payment->provider_transaction_id }}</td></tr>
        @endunless
        @if ($intent)
            <tr><td class="label">{{ __('Kumbukumbu') }}</td><td>{{ $intent->reference }}</td></tr>
        @endif
        @if ($officer)
            <tr><td class="label">{{ __('Imepokelewa na') }}</td><td>{{ $officer }}</td></tr>
        @endif
    </table>

    <div class="footer">{{ __('Asante kwa malipo yako.') }} · {{ __('Imetolewa :date', ['date' => now()->format('d/m/Y H:i')]) }}</div>
</body>
</html>
