@extends('layouts.member')

@section('title', __('Badilisha PIN'))

@section('content')
    <a href="{{ route('member.home') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-emerald-700"><x-icon name="arrow-left" class="h-4 w-4" /> {{ __('Rudi') }}</a>

    <x-card :title="__('Badilisha PIN')">
        <p class="mb-5 text-sm text-slate-500">{{ __('PIN hii inatumika hapa na kwenye USSD. Usimwambie mtu yeyote.') }}</p>
        <form method="POST" action="{{ route('member.pin') }}" class="space-y-5">
            @csrf @method('PUT')
            <x-input :label="__('PIN ya sasa')" name="current_pin" type="password" inputmode="numeric" maxlength="4" required />
            <x-input :label="__('PIN mpya')" name="pin" type="password" inputmode="numeric" maxlength="4" required />
            <x-input :label="__('Rudia PIN mpya')" name="pin_confirmation" type="password" inputmode="numeric" maxlength="4" required />
            <x-button class="w-full" icon="check">{{ __('Hifadhi PIN') }}</x-button>
        </form>
    </x-card>
@endsection
