@extends('layouts.app')
@section('title', __('Ripoti'))

@php $icons = ['income' => 'banknotes', 'payers' => 'check', 'debtors' => 'clock', 'member' => 'users']; @endphp

@section('content')
    <x-page-header :title="__('Ripoti')" :subtitle="__('Chagua ripoti, kisha kipindi: mwezi, robo mwaka au mwaka. Kila ripoti inapakuliwa kwa PDF au Excel.')" />

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($types as $key => [$title, $text])
            <a href="{{ route('reports.show', $key) }}" class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
                <span class="h-fit rounded-xl bg-emerald-50 p-3 text-emerald-700"><x-icon :name="$icons[$key]" /></span>
                <div>
                    <h2 class="font-semibold text-slate-900 group-hover:text-emerald-700">{{ __($title) }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __($text) }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endsection
