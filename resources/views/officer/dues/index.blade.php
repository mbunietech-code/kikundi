@extends('layouts.app')
@section('title', __('Ada'))

@php use App\Support\Money; $rate = $expected ? round($collected / $expected * 100) : 0; @endphp

@section('content')
    <x-page-header :title="__('Ada ya mwezi')" :subtitle="__('Kiwango cha ada, madeni ya kila mwezi na nani amelipa. Mwisho wa kulipa ada ya :period: :date.', ['period' => $period, 'date' => $deadline->format('d/m/Y')])">
        @can('manage-finance')
            <x-slot:actions>
                <form method="POST" action="{{ route('reminders.run') }}" onsubmit="return confirm(@js(__('Tuma ukumbusho unaohitajika leo? Hakuna mwanachama atakayepata ujumbe uleule mara mbili.')))">
                    @csrf <x-button variant="secondary" icon="phone">{{ __('Tuma ukumbusho') }}</x-button>
                </form>
                <form method="POST" action="{{ route('dues.generate') }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">
                    <x-button icon="plus">{{ __('Zalisha madeni ya :period', ['period' => $period]) }}</x-button>
                </form>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Kiwango cha sasa')" :value="$rule ? Money::format($rule->amount) : __('Hakijawekwa')" :hint="$rule ? __('Tangu :date', ['date' => $rule->effective_from->format('d/m/Y')]) : __('Weka kiwango hapa chini')" icon="calendar" tone="sky" />
        <x-stat :label="__('Imekusanywa :period', ['period' => $period])" :value="Money::format($collected)" :hint="__(':rate% ya :total', ['rate' => $rate, 'total' => Money::format($expected)])" icon="banknotes" tone="emerald" />
        <x-stat :label="__('Deni lililobaki')" :value="Money::format(max(0, $expected - $collected))" :hint="__(':count wanachama hawajamaliza', ['count' => $rows->where('state', '!=', 'paid')->count()])" icon="clock" tone="amber" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card :padded="false" class="xl:col-span-2">
            <x-slot:title>{{ __('Wanachama') }} · {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y') }}</x-slot:title>
            <x-slot:actions>
                <form method="GET">
                    <select name="period" onchange="this.form.submit()" class="rounded-lg border-0 py-1.5 pl-3 pr-8 text-sm ring-1 ring-inset ring-slate-300">
                        @foreach ($periods as $p)
                            <option value="{{ $p }}" @selected($p === $period)>{{ $p }}</option>
                        @endforeach
                    </select>
                </form>
            </x-slot:actions>
            @if ($rows->isEmpty())
                <x-empty icon="calendar" :title="__('Hakuna madeni ya mwezi huu')" :text="__('Bonyeza “Zalisha madeni” ili kila mwanachama hai apangiwe ada ya mwezi huu.')" />
            @else
                <x-table>
                    <x-slot:head><tr><th class="px-5 py-3">{{ __('Mwanachama') }}</th><th class="px-5 py-3 text-right">{{ __('Inadaiwa') }}</th><th class="px-5 py-3 text-right">{{ __('Imelipwa') }}</th><th class="hidden px-5 py-3 sm:table-cell">{{ __('Maendeleo') }}</th><th class="px-5 py-3">{{ __('Hali') }}</th></tr></x-slot:head>
                    @foreach ($rows as $row)
                        @php $o = $row['obligation']; $pct = $o->amount_due ? min(100, round($row['paid'] / $o->amount_due * 100)) : 0; @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('members.show', $o->member) }}" class="font-medium text-slate-900 hover:text-emerald-700">{{ $o->member->full_name }}</a>
                                <p class="text-xs text-slate-500">{{ $o->member->member_number }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">{{ Money::format($o->amount_due) }}</td>
                            <td class="px-5 py-3 text-right">{{ Money::format($row['paid']) }}</td>
                            <td class="hidden w-40 px-5 py-3 sm:table-cell"><div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-amber-400' }}" style="width: {{ $pct }}%"></div></div></td>
                            <td class="px-5 py-3"><x-status-badge :status="$row['state']" /></td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <div class="space-y-6">
            @can('manage-finance')
            <x-card :title="__('Badilisha kiwango cha ada')">
                <form method="POST" action="{{ route('dues.rule') }}" class="space-y-4">
                    @csrf
                    <x-input :label="__('Kiasi kwa mwezi (TSh)')" name="amount" type="number" min="100" :value="$rule?->amount" required />
                    <x-input :label="__('Kianze tarehe')" name="effective_from" type="date" :value="now()->startOfMonth()->format('Y-m-d')" required />
                    <p class="text-xs text-slate-500">{{ __('Madeni yaliyokwisha undwa hayabadiliki. Kiwango kipya kinatumika kwa miezi itakayozalishwa baadaye.') }}</p>
                    <x-button class="w-full">{{ __('Hifadhi kiwango') }}</x-button>
                </form>
            </x-card>
            @endcan
            <x-card :title="__('Historia ya viwango')">
                <ul class="space-y-2 text-sm">
                    @forelse ($history as $h)
                        <li class="flex justify-between"><span class="text-slate-500">{{ $h->effective_from->format('d/m/Y') }} – {{ $h->effective_to?->format('d/m/Y') ?? __('sasa') }}</span><span class="font-medium">{{ Money::format($h->amount) }}</span></li>
                    @empty
                        <li class="text-slate-500">{{ __('Bado hakuna.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
