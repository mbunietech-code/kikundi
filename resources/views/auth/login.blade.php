@extends('layouts.base')

@section('title', __('Ingia'))

@section('body')
<div class="flex min-h-full">
    <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-800 to-slate-900 lg:block">
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20"></div>
        <div class="absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-white/5"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold">{{ mb_substr(system_name(), 0, 1) }}</span>
                <span class="text-lg font-semibold">{{ system_name() }}</span>
            </div>
            <div>
                <h1 class="text-4xl font-semibold leading-tight">{{ __('Kumbukumbu za kikundi,') }}<br>{{ __('wazi na salama.') }}</h1>
                <p class="mt-4 max-w-md text-emerald-100">{{ __('Wanachama, ada, akiba na michango ya ugonjwa na msiba mahali pamoja. Wanachama wanaweza pia kutumia USSD.') }}</p>
            </div>
            <div class="grid max-w-md grid-cols-3 gap-4 text-sm">
                <div class="rounded-xl bg-white/10 p-4"><x-icon name="users" class="h-5 w-5" /><p class="mt-2">{{ __('Wanachama') }}</p></div>
                <div class="rounded-xl bg-white/10 p-4"><x-icon name="wallet" class="h-5 w-5" /><p class="mt-2">{{ __('Ada na akiba') }}</p></div>
                <div class="rounded-xl bg-white/10 p-4"><x-icon name="phone" class="h-5 w-5" /><p class="mt-2">USSD</p></div>
            </div>
        </div>
    </div>

    <div class="flex flex-1 items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center justify-between">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-lg font-bold text-white lg:invisible">{{ mb_substr(system_name(), 0, 1) }}</span>
                <x-locale-switch />
            </div>
            <h2 class="text-2xl font-semibold text-slate-900">{{ __('Karibu tena') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Ingia ili kuendelea na :name.', ['name' => system_name()]) }}</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <x-input :label="__('Barua pepe')" name="email" type="email" required autofocus autocomplete="username" />
                <x-input :label="__('Nenosiri')" name="password" type="password" required autocomplete="current-password" />
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"> {{ __('Nikumbuke') }}
                </label>
                <x-button class="w-full">{{ __('Ingia') }}</x-button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-500">{{ __('Mwanachama?') }} <a href="{{ route('member.login') }}" class="font-medium text-emerald-700 hover:underline">{{ __('Ona taarifa zako hapa') }}</a></p>
            @if (\App\Support\Settings::get('support_contact'))
                <p class="mt-8 text-center text-xs text-slate-500">{{ __('Msaada') }}: {{ \App\Support\Settings::get('support_contact') }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
