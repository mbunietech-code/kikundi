@extends('layouts.app')
@section('title', __('Kumbukumbu za kufuta'))

@php use App\Support\Money; @endphp

@section('content')
    <x-page-header :title="__('Kumbukumbu za kufuta')" :subtitle="__('Kila malipo au mwanachama aliyefutwa pamoja na malipo yake: nani alifuta, lini, kiasi na sababu.')" />

    <x-card :padded="false">
        @if ($logs->isEmpty())
            <x-empty icon="shield" :title="__('Hakuna kilichofutwa bado')" />
        @else
            <x-table>
                <x-slot:head><tr><th class="px-5 py-3">{{ __('Tarehe') }}</th><th class="px-5 py-3">{{ __('Kilichofutwa') }}</th><th class="px-5 py-3 text-right">{{ __('Kiasi') }}</th><th class="px-5 py-3">{{ __('Sababu') }}</th><th class="px-5 py-3">{{ __('Aliyefuta') }}</th></tr></x-slot:head>
                @foreach ($logs as $log)
                    <tr x-data="{ open: false }" class="align-top">
                        <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3">
                            <x-badge :tone="$log->kind === 'member' ? 'rose' : 'amber'">{{ $log->kind === 'member' ? __('Mwanachama') : __('Malipo') }}</x-badge>
                            <span class="ml-1 font-medium">{{ $log->subject }}</span>
                            @if (count($log->details['payments'] ?? []))
                                <button type="button" @click="open = !open" class="ml-2 text-xs text-emerald-700 hover:underline">{{ __('Malipo :count', ['count' => count($log->details['payments'])]) }}</button>
                                <ul x-show="open" x-cloak class="mt-2 space-y-1 text-xs text-slate-600">
                                    @foreach ($log->details['payments'] as $p)
                                        <li><span class="font-mono">{{ $p['reference'] }}</span> · {{ $p['member'] }} · {{ __($p['type']) }} · {{ Money::format($p['amount']) }} · {{ __(['succeeded' => 'Imethibitishwa', 'pending' => 'Inasubiri', 'failed' => 'Imeshindwa'][$p['status']] ?? $p['status']) }}@if ($p['transaction']) · {{ $p['transaction'] }}@endif · {{ $p['date'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-semibold">{{ Money::format($log->amount) }}</td>
                        <td class="px-5 py-3 text-slate-700">{{ $log->reason }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $log->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
            {{ $logs->links() }}
        @endif
    </x-card>
@endsection
