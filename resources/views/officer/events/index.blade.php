@extends('layouts.app')
@section('title', __('Michango'))

@php use App\Support\Money; @endphp

@section('content')
    <x-page-header :title="__('Michango maalum')" :subtitle="__('Ugonjwa, msiba, harusi, maendeleo na mengineyo. Kila mchango una kumbukumbu zake.')">
        <x-slot:actions>
            @can('manage-events')
                <x-button :href="route('events.create')" icon="plus">{{ __('Mchango mpya') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('events.index') }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ ! request('type') ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ __('Yote') }}</a>
        @foreach ($types as $key => $label)
            <a href="{{ route('events.index', ['type' => $key]) }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ request('type') === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ __($label) }}</a>
        @endforeach
    </div>

    @if ($events->isEmpty())
        <x-card><x-empty icon="heart" :title="__('Hakuna mchango bado')" :text="__('Unda mchango mpya. Ukiwa “Hai”, wanachama wanaweza kuchangia kupitia USSD.')" /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('events.show', $event) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-xl p-2.5 {{ ['bereavement' => 'bg-slate-100 text-slate-700', 'illness' => 'bg-rose-50 text-rose-600', 'wedding' => 'bg-violet-50 text-violet-600', 'development' => 'bg-sky-50 text-sky-600'][$event->event_type] ?? 'bg-amber-50 text-amber-600' }}"><x-icon name="heart" /></span>
                        <x-status-badge :status="$event->status" />
                    </div>
                    <h3 class="mt-4 font-semibold text-slate-900 group-hover:text-emerald-700">{{ $event->title }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __($types[$event->event_type] ?? '') }}@if ($event->beneficiary) · {{ $event->beneficiary->full_name }}@endif</p>
                    <div class="mt-5 flex items-end justify-between border-t border-slate-100 pt-4">
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Imekusanywa') }}</p>
                            <p class="text-lg font-semibold text-slate-900">{{ Money::format($totals[$event->id] ?? 0) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            @if ($event->target_amount) <p>{{ __('Lengo :amount', ['amount' => Money::format($event->target_amount)]) }}</p> @endif
                            <p>{{ $event->ends_at ? __('Mwisho :date', ['date' => $event->ends_at->format('d/m/Y')]) : __('Bila tarehe ya mwisho') }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
@endsection
