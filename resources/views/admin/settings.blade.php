@extends('layouts.app')
@section('title', __('Mipangilio'))

@section('content')
    <x-page-header :title="__('Mipangilio ya mfumo')" :subtitle="__('Mabadiliko yanaonekana mara moja kwenye tovuti, USSD na SMS.')" />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="grid max-w-5xl gap-6 lg:grid-cols-3" x-data="{ name: @js(old('system_name', $settings['system_name'])) }">
        @csrf
        @method('PUT')
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Utambulisho')">
                <div class="space-y-5">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ __('Jina la mfumo') }}</span>
                        <input name="system_name" x-model="name" maxlength="40" required class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                        <span class="mt-1 flex justify-between text-xs text-slate-500"><span>{{ __('Herufi 40 au chache') }}</span><span x-text="name.length + ' / 40'"></span></span>
                        @error('system_name') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <x-input :label="__('Mawasiliano ya msaada')" name="support_contact" :value="$settings['support_contact']" placeholder="0712 345 678" :hint="__('Yanaonekana kwenye ukurasa wa kuingia.')" />
                    <x-select :label="__('Lugha ya chaguo-msingi')" name="default_locale" :value="$settings['default_locale'] ?: \App\Support\Locale::default()" :options="\App\Support\Locale::SUPPORTED" />
                    <p class="-mt-3 text-xs text-slate-500">{{ __('Kwa wageni wapya, na kwa SMS na USSD za wanachama ambao hawajachagua lugha. Kila mtu anaweza kubadilisha kwa kitufe cha EN / SW.') }}</p>
                </div>
            </x-card>
            <x-card :title="__('Mipaka ya kiasi kwenye USSD')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input :label="__('Kiasi cha chini (TSh)')" name="ussd_min_amount" type="number" :value="$settings['ussd_min_amount']" />
                    <x-input :label="__('Kiasi cha juu (TSh)')" name="ussd_max_amount" type="number" :value="$settings['ussd_max_amount']" />
                </div>
            </x-card>
            @php
                $azampay = config('services.payments.driver') === 'azampay';
                $callbackToken = config('azampay.callback_token');
            @endphp
            <x-card :title="__('Malipo ya simu')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('Njia') }}</dt>
                        <dd class="text-right font-medium">
                            @if ($azampay)
                                AzamPay <x-badge :tone="config('azampay.environment') === 'production' ? 'emerald' : 'amber'">{{ config('azampay.environment') === 'production' ? __('Halisi') : 'Sandbox' }}</x-badge>
                            @else
                                {{ __('Mweka hazina anathibitisha kwa mkono') }}
                            @endif
                        </dd>
                    </div>
                    @if ($azampay)
                        <div>
                            <dt class="text-slate-500">{{ __('Callback URL ya kusajili kwa AzamPay') }}</dt>
                            <dd class="mt-1 break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs">{{ $callbackToken ? route('azampay.callback', $callbackToken) : __('Weka AZAMPAY_CALLBACK_TOKEN kwenye .env') }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="mt-4 text-xs text-slate-500">{{ __('Inabadilishwa kwenye .env (PAYMENT_GATEWAY). Kagua funguo kwa: php artisan mjeshi:azampay-check') }}</p>
            </x-card>
            <x-card :title="__('Masharti ya Katiba')">
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-input :label="__('Miezi ya neema')" name="grace_months" type="number" min="1" :value="$settings['grace_months']" :hint="__('Asipolipa miezi hii, si hai.')" />
                    <x-input :label="__('Kiingilio (TSh)')" name="entry_fee" type="number" min="0" :value="$settings['entry_fee']" :hint="__('0 = hakuna kiingilio.')" />
                    <x-input :label="__('Kiingilio cha kurejea (TSh)')" name="reinstatement_fee" type="number" min="0" :value="$settings['reinstatement_fee']" />
                    <x-input :label="__('Siku za neema za michango')" name="event_grace_days" type="number" min="0" :value="$settings['event_grace_days']" :hint="__('Baada ya tarehe ya mwisho ya mchango. 0 = usimzime.')" />
                    <x-input :label="__('Wadhamini wa lazima')" name="min_guarantors" type="number" min="0" max="3" :value="$settings['min_guarantors']" :hint="__('Kwa mwanachama mpya. 0 = si lazima.')" />
                </div>
                <label class="mt-5 flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                    <input type="hidden" name="reinstate_requires_arrears" value="0">
                    <input type="checkbox" name="reinstate_requires_arrears" value="1" @checked(old('reinstate_requires_arrears', $settings['reinstate_requires_arrears']) === '1') class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                    <span><span class="font-medium text-slate-900">{{ __('Alipe madeni yote ya ada kabla ya kurejea') }}</span><br><span class="text-slate-500">{{ __('Bila hili, kiingilio cha kurejea pekee kinatosha.') }}</span></span>
                </label>
                <p class="mt-4 text-xs text-slate-500">{{ __('Ukaguzi unafanyika kila siku saa 12 asubuhi, au kwa kubonyeza "Kagua hali" kwenye ukurasa wa Wanachama.') }}</p>
            </x-card>
            <x-card :title="__('Tarehe za malipo na ukumbusho wa SMS')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input :label="__('Siku ya mwisho ya kulipa ada')" name="dues_due_day" type="number" min="1" max="28" :value="$settings['dues_due_day']" :hint="__('Mfano 10 = kila tarehe 10 ya mwezi.')" />
                    <x-input :label="__('Siku za ukumbusho kabla ya mwisho')" name="reminder_days_before" type="number" min="0" max="14" :value="$settings['reminder_days_before']" />
                </div>
                <label class="mt-5 flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                    <input type="hidden" name="reminders_enabled" value="0">
                    <input type="checkbox" name="reminders_enabled" value="1" @checked(old('reminders_enabled', $settings['reminders_enabled']) === '1') class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                    <span><span class="font-medium text-slate-900">{{ __('Tuma ukumbusho wa SMS wenyewe') }}</span><br><span class="text-slate-500">{{ __('Kabla ya tarehe ya mwisho ya ada na ya michango, na siku moja baada ya ada kuchelewa. Kila SMS ina gharama ya Beem.') }}</span></span>
                </label>
                <div class="mt-5 max-w-xs"><x-input :label="__('Siku za kuhifadhi nakala za akiba')" name="backup_keep_days" type="number" min="1" max="365" :value="$settings['backup_keep_days']" /></div>
                <p class="mt-4 text-xs text-slate-500">{{ __('Inaendeshwa kila siku saa 2 asubuhi, au kwa kitufe cha "Tuma ukumbusho" kwenye ukurasa wa Ada. Ada ya mwezi mpya huzalishwa yenyewe siku ya kwanza.') }}</p>
            </x-card>
            <div class="flex justify-end"><x-button icon="check">{{ __('Hifadhi mipangilio') }}</x-button></div>
        </div>

        <x-card :title="__('Mwonekano wa USSD')">
            <div class="mx-auto w-56 rounded-[2rem] bg-slate-900 p-3 shadow-xl">
                <div class="rounded-[1.4rem] bg-slate-100 p-4 font-mono text-[11px] leading-relaxed text-slate-800">
                    <p x-text="name || @js(__('Jina la mfumo'))" class="font-semibold"></p>
                    @foreach (explode("\n", __(\App\Services\Ussd\UssdMenuService::MAIN_MENU)) as $line)<p>{{ $line }}</p>@endforeach
                    <div class="mt-3 rounded bg-white px-2 py-1 text-slate-400">{{ __('Jibu…') }}</div>
                </div>
            </div>
            <p class="mt-4 text-center text-xs text-slate-500">{{ __('Hivi ndivyo menyu kuu inavyoonekana kwenye simu ya mwanachama.') }}</p>
        </x-card>
    </form>
@endsection
