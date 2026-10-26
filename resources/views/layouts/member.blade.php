@extends('layouts.base')

@section('body')
<div class="min-h-full">
    <header class="bg-gradient-to-br from-emerald-700 to-emerald-900 text-white">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
            <a href="{{ route('member.home') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 font-bold">{{ mb_substr(system_name(), 0, 1) }}</span>
                <span class="truncate font-semibold">{{ system_name() }}</span>
            </a>
            <div class="ml-auto flex items-center gap-1">
                <x-locale-switch dark class="mr-1" />
                <a href="{{ route('member.pin') }}" class="rounded-lg p-2 text-emerald-100 hover:bg-white/10 hover:text-white" title="{{ __('Badilisha PIN') }}"><x-icon name="key" /></a>
                <form method="POST" action="{{ route('member.logout') }}">
                    @csrf
                    <button class="rounded-lg p-2 text-emerald-100 hover:bg-white/10 hover:text-white" title="{{ __('Toka') }}"><x-icon name="logout" /></button>
                </form>
            </div>
        </div>
        @yield('hero')
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
@endsection
