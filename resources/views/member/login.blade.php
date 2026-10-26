@extends('layouts.base')

@section('title', __('Ingia kama mwanachama'))

@section('body')
<div class="flex min-h-full flex-col bg-gradient-to-b from-emerald-700 to-emerald-900 px-4 py-10">
    <div class="mx-auto w-full max-w-sm">
        <div class="mb-8 flex items-center gap-3 text-white">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold">{{ mb_substr(system_name(), 0, 1) }}</span>
            <span class="text-lg font-semibold">{{ system_name() }}</span>
            <x-locale-switch dark class="ml-auto" />
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-xl">
            <h1 class="text-xl font-semibold text-slate-900">{{ __('Karibu, mwanachama') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Tumia namba yako ya simu na PIN ileile ya USSD.') }}</p>

            <form method="POST" action="{{ route('member.login') }}" class="mt-6 space-y-5">
                @csrf
                <x-input :label="__('Namba ya simu')" name="phone" type="tel" inputmode="tel" placeholder="0712345678" required autofocus autocomplete="tel" />
                <x-input :label="__('PIN (tarakimu 4)')" name="pin" type="password" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="••••" required autocomplete="current-password" />
                <x-button class="w-full">{{ __('Ingia') }}</x-button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-500">{{ __('Hujawekewa PIN au umeisahau? Wasiliana na katibu wa kikundi.') }}</p>
        </div>

        <p class="mt-6 text-center text-sm text-emerald-100">{{ __('Kiongozi?') }} <a href="{{ route('login') }}" class="font-medium text-white underline">{{ __('Ingia hapa') }}</a></p>
    </div>
</div>
@endsection
