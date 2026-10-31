@extends('layouts.member')

@section('title', __('Taarifa zangu'))

@php
    use App\Support\Money;
    use App\Services\Reports\ReportService;
    $owed = array_sum($debt);
@endphp

@section('hero')
    <div class="mx-auto max-w-3xl px-4 pb-6 pt-2">
        <div class="flex items-center gap-4">
            <x-avatar :member="$member" size="h-14 w-14 text-xl" class="ring-2 ring-white/30" />
            <div class="min-w-0">
                <p class="text-sm text-emerald-100">{{ __('Habari,') }}</p>
                <h1 class="truncate text-xl font-semibold">{{ $member->full_name }}</h1>
                <p class="text-sm text-emerald-100">{{ $member->member_number }} · <span class="font-medium text-white">{{ __(['active' => 'Mwanachama hai', 'inactive' => 'Sio mwanachama hai', 'pending' => 'Anasubiri kiingilio', 'suspended' => 'Amesimamishwa'][$member->status] ?? $member->status) }}</span></p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white/10 p-4">
                <p class="text-xs text-emerald-100">{{ __('Deni langu') }}</p>
                <p class="mt-1 text-xl font-semibold">{{ Money::format($owed) }}</p>
            </div>
            <div class="rounded-2xl bg-white/10 p-4">
                <p class="text-xs text-emerald-100">{{ __('Akiba yangu') }}</p>
                <p class="mt-1 text-xl font-semibold">{{ Money::format($savings) }}</p>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="space-y-6">
        @if ($missing)
            <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
                <p class="font-semibold text-amber-900">{{ $member->status === 'pending' ? __('Ili uanachama wako uanze:') : __('Ili urejee kuwa mwanachama hai:') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-amber-900">
                    @foreach ($missing as $item) <li>• {{ $item === __('Kurejea hakujaanzishwa') ? __('Wasiliana na mweka hazina ili kuanzisha kurejea') : __('Lipa :item', ['item' => $item]) }}</li> @endforeach
                </ul>
            </div>
        @endif

        @if ($owed)
            <x-card :title="__('Ninachodaiwa')">
                <dl class="space-y-2 text-sm">
                    @if ($debt['dues']) <div class="flex justify-between"><dt class="text-slate-500">{{ __('Ada') }}</dt><dd class="font-medium">{{ Money::format($debt['dues']) }}</dd></div> @endif
                    @if ($debt['fees']) <div class="flex justify-between"><dt class="text-slate-500">{{ __('Kiingilio') }}</dt><dd class="font-medium">{{ Money::format($debt['fees']) }}</dd></div> @endif
                    @if ($debt['events']) <div class="flex justify-between"><dt class="text-slate-500">{{ __('Michango') }}</dt><dd class="font-medium">{{ Money::format($debt['events']) }}</dd></div> @endif
                    <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold"><dt>{{ __('Jumla') }}</dt><dd class="text-rose-600">{{ Money::format($owed) }}</dd></div>
                </dl>
                <p class="mt-4 rounded-lg p-3 text-xs {{ $deadline->isPast() ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-600' }}">
                    @if ($deadline->isPast())
                        {!! __('Muda wa kulipa ada ya mwezi huu ulikwisha :date. Tafadhali lipa haraka', ['date' => '<span class="font-semibold">'.$deadline->format('d/m/Y').'</span>']) !!}
                    @else
                        {!! __('Ada ya mwezi huu inatakiwa kabla ya :date', ['date' => '<span class="font-semibold">'.$deadline->format('d/m/Y').'</span>']) !!}
                    @endif
                    {{ __('kupitia USSD (1. Ada) au kwa mweka hazina.') }}
                </p>
            </x-card>
        @endif

        @if ($announcements->isNotEmpty())
            <x-card :title="__('Matangazo')">
                <div class="space-y-4">
                    @foreach ($announcements as $a)
                        <article>
                            <h3 class="font-medium text-slate-900">{{ $a->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $a->body }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $a->published_at?->format('d/m/Y') }}</p>
                        </article>
                    @endforeach
                </div>
            </x-card>
        @endif

        @if ($events->isNotEmpty())
            <x-card :title="__('Michango inayoendelea')" :padded="false">
                @foreach ($events as ['event' => $event, 'owed' => $eventOwed])
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $event->title }}</p>
                            <p class="text-xs text-slate-500">{{ __($event->typeLabel()) }}@if ($event->ends_at) · {{ __('mwisho :date', ['date' => $event->ends_at->format('d/m/Y')]) }}@endif</p>
                        </div>
                        <div class="text-right">
                            @if ($event->amount_per_member)
                                @if ($eventOwed) <p class="text-sm font-semibold text-rose-600">{{ Money::format($eventOwed) }}</p><p class="text-xs text-slate-500">{{ __('unadaiwa') }}</p>
                                @else <x-badge tone="emerald">{{ __('Umelipa') }}</x-badge> @endif
                            @else
                                <p class="text-xs text-slate-500">{{ __('Kwa hiari') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-card>
        @endif

        <x-card :title="__('Ada zangu')" :padded="false">
            @forelse ($dues as ['obligation' => $o, 'paid' => $paid])
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ \Carbon\Carbon::createFromFormat('Y-m', $o->period)->translatedFormat('F Y') }}</p>
                        <p class="text-xs text-slate-500">{{ __(':paid kati ya :due', ['paid' => Money::format($paid), 'due' => Money::format($o->amount_due)]) }}</p>
                    </div>
                    <x-status-badge :status="$paid >= $o->amount_due ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')" />
                </div>
            @empty
                <x-empty icon="calendar" :title="__('Hakuna ada iliyopangwa bado')" />
            @endforelse
        </x-card>

        <x-card :title="__('Malipo yangu')" :padded="false">
            @forelse ($payments as $p)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ __(ReportService::TARGETS[$p->target_type] ?? $p->target_type) }}
                            @if ($p->payer_member_id !== $p->credited_member_id)
                                <span class="text-xs font-normal text-slate-500">· {{ $p->payer_member_id === $member->id ? __('kwa :name', ['name' => $p->creditedMember->full_name]) : __('kutoka :name', ['name' => $p->payer->full_name]) }}</span>
                            @endif
                        </p>
                        <p class="font-mono text-xs text-slate-500">{{ $p->payment?->receipt_number ?? $p->reference }} · {{ $p->created_at->format('d/m/Y') }}</p>
                        @if ($p->payment)
                            <a href="{{ route('member.receipt', $p->payment) }}" target="_blank" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('Pakua risiti') }}</a>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold">{{ Money::format($p->amount) }}</p>
                        <x-status-badge :status="$p->status" />
                    </div>
                </div>
            @empty
                <x-empty icon="banknotes" :title="__('Bado hakuna malipo')" />
            @endforelse
        </x-card>

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-button :href="route('member.statement')" variant="secondary" icon="download" class="flex-1">{{ __('Pakua taarifa yangu (:year) PDF', ['year' => now()->year]) }}</x-button>
            <x-button :href="route('member.pin')" variant="secondary" icon="key" class="flex-1">{{ __('Badilisha PIN') }}</x-button>
        </div>
    </div>
@endsection
