@extends('layouts.app')
@section('title', __('Akiba'))

@php use App\Support\Money; @endphp

@section('content')
    <x-page-header :title="__('Akiba')" :subtitle="__('Akiba ya kila mwanachama na jumla ya kikundi, kutoka malipo yaliyothibitishwa tu.')">
        <x-slot:actions>
            @can('manage-finance')
                <x-button :href="route('payments.create', ['target' => 'savings'])" icon="plus">{{ __('Rekodi akiba') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Akiba ya kikundi')" :value="Money::format($total)" icon="wallet" tone="violet" />
        <x-stat :label="__('Wanaoweka akiba')" :value="$savers.' / '.$rows->count()" icon="users" tone="sky" />
        <x-stat :label="__('Wastani kwa mwanachama')" :value="Money::format($savers ? intdiv($total, $savers) : 0)" icon="chart" tone="emerald" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card :title="__('Akiba kwa mwanachama')" :padded="false" class="xl:col-span-2">
            <x-table>
                <x-slot:head><tr><th class="px-5 py-3">{{ __('Mwanachama') }}</th><th class="hidden px-5 py-3 sm:table-cell">{{ __('Mara ya mwisho') }}</th><th class="px-5 py-3 text-right">{{ __('Akiba') }}</th></tr></x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-5 py-3"><a href="{{ route('members.show', $row['member']) }}" class="font-medium hover:text-emerald-700">{{ $row['member']->full_name }}</a> <span class="text-xs text-slate-500">{{ $row['member']->member_number }}</span></td>
                        <td class="hidden px-5 py-3 text-slate-500 sm:table-cell">{{ $row['last_at'] ? \Carbon\Carbon::parse($row['last_at'])->format('d/m/Y') : '—' }}</td>
                        <td class="px-5 py-3 text-right font-semibold {{ $row['total'] ? 'text-slate-900' : 'text-slate-400' }}">{{ Money::format($row['total']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-card :title="__('Akiba za karibuni')">
            <ul class="space-y-3">
                @forelse ($recent as $a)
                    <li class="flex items-center justify-between text-sm">
                        <span><span class="font-medium">{{ $memberNames[$a->credited_member_id] ?? '' }}</span><br><span class="text-xs text-slate-500">{{ $a->created_at->diffForHumans() }}</span></span>
                        <span class="font-semibold text-emerald-700">+{{ Money::format($a->amount) }}</span>
                    </li>
                @empty
                    <li><x-empty icon="wallet" :title="__('Bado hakuna akiba')" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>
@endsection
