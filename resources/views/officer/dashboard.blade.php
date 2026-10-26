@extends('layouts.app')
@section('title', __('Dashibodi'))

@php
    use App\Support\Money;
    $rate = $duesExpected ? round($duesCollected / $duesExpected * 100) : 0;
    $max = max(1, $months->max('amount'));
@endphp

@section('content')
    <x-page-header :title="__('Habari, :name', ['name' => explode(' ', auth()->user()->name)[0]])" :subtitle="__('Muhtasari wa kikundi kwa :month', ['month' => now()->translatedFormat('F Y')])">
        <x-slot:actions>
            @can('manage-finance')
                <x-button :href="route('payments.create')" icon="plus">{{ __('Rekodi malipo') }}</x-button>
            @endcan
            @can('manage-membership')
                <x-button :href="route('members.create')" variant="secondary" icon="user-plus">{{ __('Mwanachama mpya') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $canMembers = auth()->user()->can('view-membership');
        $canFinance = auth()->user()->can('view-finance');
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Wanachama hai')" :value="number_format($membersActive)" :hint="$membersPending ? __(':count wanasubiri kiingilio', ['count' => $membersPending]) : __('Wenye haki zote za uanachama')" icon="users" tone="sky" :href="$canMembers ? route('members.index') : null" />
        <x-stat :label="__('Wasio hai')" :value="number_format($membersInactive)" :hint="__('Wamepoteza uanachama kwa kutolipa')" icon="shield" :tone="$membersInactive ? 'rose' : 'emerald'" :href="$canMembers ? route('members.index', ['status' => 'inactive']) : null" />
        <x-stat :label="__('Jumla ya mapato')" :value="Money::format($totalIncome)" :hint="__('Ada, akiba, michango na viingilio')" icon="banknotes" tone="emerald" :href="$canFinance ? route('payments.index', ['status' => 'succeeded']) : null" />
        <x-stat :label="__('Jumla ya madeni')" :value="Money::format($totalDebt)" :hint="__(':count wanadaiwa ada, kiingilio au michango', ['count' => $debtorCount])" icon="clock" :tone="$totalDebt ? 'amber' : 'emerald'" :href="$canFinance ? route('dues.index') : null" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Ada mwezi huu')" :value="Money::format($duesCollected)" :hint="__(':rate% ya :total', ['rate' => $rate, 'total' => Money::format($duesExpected)])" icon="calendar" tone="emerald" :href="$canFinance ? route('dues.index') : null" />
        <x-stat :label="__('Akiba ya kikundi')" :value="Money::format($groupSavings)" :hint="__('Kutoka malipo yaliyothibitishwa')" icon="wallet" tone="violet" :href="$canFinance ? route('savings.index') : null" />
        <x-stat :label="__('Malipo yanayosubiri')" :value="number_format($pendingCount)" :hint="__(':amount yanasubiri uthibitisho', ['amount' => Money::format($pendingAmount)])" icon="clock" tone="amber" :href="$canFinance ? route('payments.index') : null" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card :title="__('Makusanyo ya miezi 6')" class="xl:col-span-2">
            <div class="flex h-56 items-end gap-3 sm:gap-6">
                @foreach ($months as $m)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-xs font-medium text-slate-500">{{ $m['amount'] ? number_format($m['amount'] / 1000, 0).'k' : '' }}</span>
                        <div class="w-full rounded-t-lg {{ $loop->last ? 'bg-emerald-500' : 'bg-emerald-200' }}" style="height: {{ max(4, $m['amount'] / $max * 170) }}px"></div>
                        <span class="text-xs text-slate-500">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">{{ __('Ukusanyaji wa ada :period', ['period' => $period]) }}</span>
                    <span class="font-semibold text-slate-900">{{ $rate }}%</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, $rate) }}%"></div></div>
            </div>
        </x-card>

        <x-card :title="__('Michango inayoendelea')">
            <x-slot:actions><a href="{{ route('events.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">{{ __('Zote') }}</a></x-slot:actions>
            <div class="space-y-4">
                @forelse ($events as [$event, $total])
                    <a href="{{ route('events.show', $event) }}" class="flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-slate-50">
                        <span class="rounded-lg p-2 {{ ['bereavement' => 'bg-slate-100 text-slate-600', 'illness' => 'bg-rose-50 text-rose-600', 'wedding' => 'bg-violet-50 text-violet-600', 'development' => 'bg-sky-50 text-sky-600'][$event->event_type] ?? 'bg-amber-50 text-amber-600' }}"><x-icon name="heart" class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $event->title }}</p>
                            <p class="text-xs text-slate-500">{{ __($event->typeLabel()) }}</p>
                        </div>
                        <span class="text-sm font-semibold text-slate-900">{{ Money::format($total) }}</span>
                    </a>
                @empty
                    <x-empty icon="heart" :title="__('Hakuna mchango hai')" :text="__('Michango maalum inayoendelea itaonekana hapa.')" />
                @endforelse
            </div>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <x-card :title="__('Wadaiwa')" :padded="false">
            @can('manage-finance')
                @if ($debtorCount)
                    <x-slot:actions>
                        <form method="POST" action="{{ route('reminders.debts') }}" onsubmit="return confirm(@js(__('Tuma SMS ya deni kwa wadaiwa :count? Kila SMS ina gharama.', ['count' => $debtorCount])))">
                            @csrf <button class="text-sm font-medium text-emerald-700 hover:underline">{{ __('Tuma SMS ya deni') }}</button>
                        </form>
                    </x-slot:actions>
                @endif
            @endcan
            @forelse ($debtors as [$member, $owed])
                <a @if ($canMembers) href="{{ route('members.show', $member) }}" @endif class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0 {{ $canMembers ? 'hover:bg-slate-50' : '' }}">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-avatar :member="$member" class="h-8 w-8 text-xs" size="" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $member->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $member->member_number }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-rose-600">{{ Money::format($owed) }}</p>
                        @unless ($member->isActive()) <x-status-badge :status="$member->status" /> @endunless
                    </div>
                </a>
            @empty
                <x-empty icon="check" :title="__('Hakuna mdaiwa')" :text="__('Kila mwanachama amemaliza ada na kiingilio.')" />
            @endforelse
        </x-card>

        <x-card :title="__('Yanasubiri uthibitisho')" :padded="false">
            @can('manage-finance')
                <x-slot:actions><a href="{{ route('payments.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">{{ __('Thibitisha') }}</a></x-slot:actions>
            @endcan
            @forelse ($pending as $p)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $p->creditedMember->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $p->reference }} · {{ $p->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold">{{ Money::format($p->amount) }}</p>
                        <x-status-badge :status="$p->status" />
                    </div>
                </div>
            @empty
                <x-empty icon="check" :title="__('Hakuna kinachosubiri')" :text="__('Maombi ya malipo kutoka USSD yataonekana hapa.')" />
            @endforelse
        </x-card>

        <x-card :title="__('Malipo ya karibuni')" :padded="false">
            @forelse ($recent as $p)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $p->creditedMember->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $p->reference }} · {{ $p->updated_at->diffForHumans() }}</p>
                    </div>
                    <p class="text-sm font-semibold text-emerald-700">+{{ Money::format($p->amount) }}</p>
                </div>
            @empty
                <x-empty icon="banknotes" :title="__('Bado hakuna malipo')" :text="__('Malipo yaliyothibitishwa yataonekana hapa.')" />
            @endforelse
        </x-card>
    </div>
@endsection
