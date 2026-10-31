@extends('layouts.app')
@section('title', __('SMS na malipo'))

@php
    $secret = function (string $name, string $label, ?string $hint = null) use ($saved) {
        return compact('name', 'label', 'hint') + ['saved' => $saved[$name] ?? false];
    };
@endphp

@section('content')
    <x-page-header :title="__('SMS na malipo')" :subtitle="__('Funguo za Beem (SMS) na ClickPesa (malipo kwa simu). Funguo za siri huhifadhiwa kwa usimbaji na hazionyeshwi tena.')" />

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.integrations.update') }}" class="space-y-6 lg:col-span-2">
            @csrf
            @method('PUT')

            <x-card :title="__('Beem SMS')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-select :label="__('Kutuma SMS')" name="sms_driver" :value="$smsDriver"
                        :options="['beem' => __('Tuma SMS halisi kupitia Beem'), 'log' => __('Usitume (andika kwenye log tu)')]" />
                    <x-input :label="__('Jina la mtumaji (Sender ID)')" name="beem_sender_id" :value="$settings['beem_sender_id'] ?: config('beem.sms.sender_id')" maxlength="11"
                        :hint="__('Lazima liwe limeidhinishwa na Beem. Herufi 11 au chache.')" />
                    @foreach ([$secret('beem_api_key', __('API key')), $secret('beem_secret_key', __('Secret key'))] as $field)
                        @include('admin.partials.secret-input', $field)
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-slate-500">{{ __('Funguo zinapatikana kwenye Beem: Profile → Authentication Information.') }}</p>
            </x-card>

            <x-card :title="__('ClickPesa (malipo kwa simu)')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-select :label="__('Njia ya kukusanya malipo ya USSD')" name="payment_gateway" :value="$gateway"
                            :options="['manual' => __('Mweka hazina athibitishe kwa mkono'), 'clickpesa' => 'ClickPesa', 'azampay' => 'AzamPay']" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Ukichagua ClickPesa, mwanachama anapolipa kwa USSD anapata ombi la kuweka PIN kwenye simu yake.') }}</p>
                    </div>
                    <x-input :label="__('Client ID')" name="clickpesa_client_id" :value="$settings['clickpesa_client_id']" autocomplete="off" />
                    @include('admin.partials.secret-input', $secret('clickpesa_api_key', __('API key')))
                    <div class="sm:col-span-2">
                        @include('admin.partials.secret-input', $secret('clickpesa_checksum_key', __('Checksum key (si lazima)'), __('Jaza tu kama umewasha checksum kwenye ClickPesa.')))
                    </div>
                </div>
                <div class="mt-5 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                    <p class="font-medium text-slate-800">{{ __('Webhook URL ya kuweka kwenye ClickPesa') }}</p>
                    <p class="mt-1 break-all font-mono">{{ $webhookUrl }}</p>
                    <p class="mt-1">{{ __('ClickPesa Dashboard → Settings → Webhooks. Mfumo unathibitisha kila malipo kwa kuuliza ClickPesa kabla ya kuyarekodi.') }}</p>
                </div>
            </x-card>

            <div class="flex justify-end"><x-button icon="check">{{ __('Hifadhi') }}</x-button></div>
        </form>

        <div class="space-y-6">
            <x-card :title="__('Salio la Beem')">
                @if ($beemBalance !== null)
                    <p class="text-3xl font-semibold text-slate-900">{{ number_format($beemBalance) }}</p>
                    <p class="text-sm text-slate-500">{{ __('SMS zilizobaki kwenye akaunti') }}</p>
                    @if ($beemBalance < 100)
                        <p class="mt-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">{{ __('Salio linakaribia kuisha. Nunua SMS zaidi kwenye Beem.') }}</p>
                    @endif
                @elseif ($beemError)
                    <p class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700">{{ __('Salio halikupatikana: :reason', ['reason' => $beemError]) }}</p>
                @else
                    <p class="text-sm text-slate-500">{{ __('Weka funguo za Beem kuona salio.') }}</p>
                @endif

                @if ($beemConfigured)
                    <form method="POST" action="{{ route('admin.integrations.test-sms') }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                        @csrf
                        <x-input :label="__('Tuma SMS ya jaribio kwa')" name="phone" placeholder="0712345678" inputmode="tel" />
                        <x-button variant="secondary" class="w-full">{{ __('Tuma jaribio') }}</x-button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Salio la ClickPesa')">
                @if ($clickpesaBalance !== null)
                    @forelse ($clickpesaBalance as $row)
                        <p class="text-2xl font-semibold text-slate-900">{{ $row['currency'] }} {{ number_format($row['balance']) }}</p>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Hakuna salio lililorudishwa.') }}</p>
                    @endforelse
                @elseif ($clickpesaError)
                    <p class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700">{{ __('Salio halikupatikana: :reason', ['reason' => $clickpesaError]) }}</p>
                @else
                    <p class="text-sm text-slate-500">{{ __('Weka funguo za ClickPesa kuona salio.') }}</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
