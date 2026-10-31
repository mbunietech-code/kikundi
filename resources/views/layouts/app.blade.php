@extends('layouts.base')

@php
    $nav = [
        ['Dashibodi', 'dashboard', 'home', 'officer'],
        ['Wanachama', 'members.index', 'users', 'view-membership'],
        ['Ada', 'dues.index', 'calendar', 'view-finance'],
        ['Akiba', 'savings.index', 'wallet', 'view-finance'],
        ['Michango', 'events.index', 'heart', 'officer'],
        ['Malipo', 'payments.index', 'banknotes', 'view-finance'],
        ['Matangazo', 'announcements.index', 'megaphone', 'view-membership'],
        ['Ripoti', 'reports.index', 'chart', 'view-finance'],
    ];
    $systemNav = [
        ['Watumiaji', 'users.index', 'shield', 'manage-system'],
        ['Mipangilio', 'admin.settings.edit', 'cog', 'manage-system'],
        ['SMS na malipo', 'admin.integrations.edit', 'banknotes', 'manage-system'],
        ['Nakala za akiba', 'admin.backups.index', 'download', 'manage-system'],
        ['Kumbukumbu za kufuta', 'admin.deletions.index', 'trash', 'manage-system'],
    ];
    $isActive = fn (string $route) => request()->routeIs(str_replace('.index', '', $route).'*');
@endphp

@section('body')
<div x-data="{ open: false }" class="min-h-full">
    {{-- Mobile sidebar overlay --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="open = false"></div>

    <aside :class="{ '!translate-x-0': open }"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500 font-bold text-white">{{ mb_substr(system_name(), 0, 1) }}</span>
            <span class="truncate font-semibold text-white">{{ system_name() }}</span>
            <button class="ml-auto lg:hidden" @click="open = false" aria-label="{{ __('Funga menyu') }}"><x-icon name="x" /></button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as [$label, $route, $icon, $ability])
                @can($ability)
                    <a href="{{ route($route) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive($route) ? 'bg-emerald-600 text-white' : 'hover:bg-white/5 hover:text-white' }}">
                        <x-icon :name="$icon" /> {{ __($label) }}
                    </a>
                @endcan
            @endforeach
            @can('manage-system')
                <p class="px-3 pb-1 pt-5 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Mfumo') }}</p>
                @foreach ($systemNav as [$label, $route, $icon, $ability])
                    <a href="{{ route($route) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive($route) ? 'bg-emerald-600 text-white' : 'hover:bg-white/5 hover:text-white' }}">
                        <x-icon :name="$icon" /> {{ __($label) }}
                    </a>
                @endforeach
            @endcan
        </nav>
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ __(\App\Support\Roles::label(auth()->user()->role)) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button class="rounded-lg p-1.5 hover:bg-white/10 hover:text-white" title="{{ __('Toka') }}"><x-icon name="logout" /></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" @click="open = true" aria-label="{{ __('Fungua menyu') }}"><x-icon name="menu" /></button>
            <p class="text-sm text-slate-500">{{ \App\Support\GroupContext::for(auth()->user())->name }}</p>
            <p class="ml-auto hidden text-sm text-slate-500 sm:block">{{ now()->translatedFormat('l, d F Y') }}</p>
            <x-locale-switch class="ml-auto sm:ml-0" />
        </header>

        <main class="px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <x-icon name="check" class="mt-0.5 h-4 w-4" />
                    <span class="flex-1">{{ session('status') }}</span>
                    <button @click="show = false" aria-label="{{ __('Funga') }}"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@endsection
