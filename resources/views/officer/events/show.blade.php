@extends('layouts.app')
@section('title', $event->title)

@php use App\Support\Money; @endphp

@section('content')
    <x-page-header :title="$event->title" :subtitle="__($types[$event->event_type] ?? '').($event->beneficiary ? ' · '.__('Mnufaika: :name', ['name' => $event->beneficiary->full_name]) : '')" :back="route('events.index')">
        <x-slot:actions>
            @can('manage-finance')
                <x-button :href="route('payments.create', ['target' => 'event', 'event' => $event->id])" icon="plus">{{ __('Rekodi mchango') }}</x-button>
            @endcan
            @can('manage-events')
                <x-button :href="route('events.edit', $event)" variant="secondary">{{ __('Hariri') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $owing = collect($expected)->where('balance', '>', 0);
        $goal = $event->target_amount ?: ($event->amount_per_member ? $event->amount_per_member * count($expected) : null);
    @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Imekusanywa')" :value="Money::format($total)" icon="banknotes" tone="emerald"
            :hint="$goal ? ($event->target_amount ? __(':rate% ya lengo :amount', ['rate' => round($total / $goal * 100), 'amount' => Money::format($goal)]) : __(':rate% ya kinachotarajiwa :amount', ['rate' => round($total / $goal * 100), 'amount' => Money::format($goal)])) : null" />
        <x-stat :label="__('Wachangiaji')" :value="$contributions->pluck('credited_member_id')->unique()->count()" icon="users" tone="sky" />
        <x-stat :label="__('Hali')" :value="__($statuses[$event->status] ?? $event->status)" :hint="($event->starts_at ? __('Kuanzia :date', ['date' => $event->starts_at->format('d/m/Y')]) : '').($event->ends_at ? ' · '.__('mwisho :date', ['date' => $event->ends_at->format('d/m/Y')]) : '')" icon="clock" tone="amber" />
    </div>

    @if ($goal)
        <div class="mt-4 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, $total / $goal * 100) }}%"></div></div>
    @endif

    @if ($expected)
        <x-card class="mt-6" :padded="false">
            <x-slot:title>{{ __('Kila mwanachama') }} · {{ Money::format($event->amount_per_member) }}</x-slot:title>
            <x-slot:actions><span class="text-sm text-slate-500">{{ __(':count wanadaiwa :amount', ['count' => $owing->count(), 'amount' => Money::format($owing->sum('balance'))]) }}</span></x-slot:actions>
            <x-table>
                <x-slot:head><tr><th class="px-5 py-3">{{ __('Mwanachama') }}</th><th class="px-5 py-3 text-right">{{ __('Amelipa') }}</th><th class="px-5 py-3 text-right">{{ __('Deni') }}</th><th class="px-5 py-3">{{ __('Hali') }}</th></tr></x-slot:head>
                @foreach ($expected as $row)
                    <tr>
                        <td class="px-5 py-3"><span class="font-medium text-slate-900">{{ $row['member']->full_name }}</span> <span class="text-xs text-slate-500">{{ $row['member']->member_number }}</span></td>
                        <td class="px-5 py-3 text-right">{{ Money::format($row['paid']) }}</td>
                        <td class="px-5 py-3 text-right font-medium {{ $row['balance'] ? 'text-rose-600' : '' }}">{{ Money::format($row['balance']) }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$row['balance'] === 0 ? 'paid' : ($row['paid'] ? 'partial' : 'unpaid')" /></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card :title="__('Michango iliyothibitishwa')" :padded="false" class="xl:col-span-2">
            @if ($contributions->isEmpty())
                <x-empty icon="heart" :title="__('Bado hakuna mchango')" :text="__('Michango ya USSD itaonekana hapa baada ya kuthibitishwa.')" />
            @else
                <x-table>
                    <x-slot:head><tr><th class="px-5 py-3">{{ __('Mwanachama') }}</th><th class="px-5 py-3">{{ __('Tarehe') }}</th><th class="px-5 py-3 text-right">{{ __('Kiasi') }}</th></tr></x-slot:head>
                    @foreach ($contributions as $c)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $memberNames[$c->credited_member_id] ?? '' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3 text-right font-semibold">{{ Money::format($c->amount) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
        <x-card :title="__('Maelezo')">
            <p class="whitespace-pre-line text-sm text-slate-600">{{ $event->description ?: __('Hakuna maelezo.') }}</p>
            <div class="mt-5 rounded-xl bg-slate-50 p-4 text-xs text-slate-600">
                {{ __('Kwenye USSD:') }} <span class="font-medium">{{ __(['illness' => '2. Ugonjwa', 'bereavement' => '3. Msiba'][$event->event_type] ?? '8. Michango mingine') }} → {{ $event->title }}</span>
                @if ($event->status !== 'active') <br><span class="text-amber-700">{{ __('Haionekani kwenye USSD hadi iwe “Hai”.') }}</span> @endif
            </div>
        </x-card>
    </div>
@endsection
