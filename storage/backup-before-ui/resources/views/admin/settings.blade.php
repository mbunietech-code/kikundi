@extends('layouts.app')

@section('title', 'Mipangilio')

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4 rounded-lg bg-white p-6 shadow">
        @csrf
        @method('PUT')
        <h1 class="text-xl font-semibold">Mipangilio ya mfumo</h1>

        @if (session('status'))
            <p class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
        @endif

        <label class="block">
            <span class="text-sm">Jina la mfumo</span>
            <input name="system_name" value="{{ old('system_name', $settings['system_name']) }}" maxlength="40" required class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
            <span class="text-xs text-gray-500">Linaonekana kwenye tovuti, menyu ya USSD na SMS. Herufi 40 au chache.</span>
            @error('system_name') <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="text-sm">Mawasiliano ya msaada</span>
            <input name="support_contact" value="{{ old('support_contact', $settings['support_contact']) }}" class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
        </label>

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm">Kiasi cha chini USSD (TSh)</span>
                <input type="number" name="ussd_min_amount" value="{{ old('ussd_min_amount', $settings['ussd_min_amount']) }}" class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
                @error('ussd_min_amount') <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm">Kiasi cha juu USSD (TSh)</span>
                <input type="number" name="ussd_max_amount" value="{{ old('ussd_max_amount', $settings['ussd_max_amount']) }}" class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
                @error('ussd_max_amount') <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <button class="rounded bg-emerald-700 px-4 py-2 text-white">Hifadhi</button>
    </form>
@endsection
