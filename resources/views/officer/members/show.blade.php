@extends('layouts.app')
@section('title', $member->full_name)

@php use App\Support\Money; use App\Http\Controllers\Officer\DependantController; use App\Http\Controllers\Officer\MemberController; @endphp

@section('content')
    <x-page-header :title="$member->full_name" :back="route('members.index')">
        <x-slot:actions>
            @can('manage-finance')
                <x-button :href="route('payments.create', ['member' => $member->id])" icon="plus">{{ __('Rekodi malipo') }}</x-button>
            @endcan
            @can('view-finance')
                <x-button :href="route('reports.show', ['member', 'member' => $member->id, 'type' => 'year'])" variant="secondary" icon="chart">{{ __('Ripoti') }}</x-button>
            @endcan
            @can('manage-membership')
                <x-button :href="route('members.edit', $member)" variant="secondary">{{ __('Hariri') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6">
            <x-card>
                <div class="flex items-center gap-4">
                    <x-avatar :member="$member" size="h-14 w-14 text-xl" />
                    <div>
                        <p class="font-semibold text-slate-900">{{ $member->member_number }}</p>
                        <x-status-badge :status="$member->status" />
                    </div>
                </div>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Simu') }}</dt><dd class="font-medium">+{{ $member->phone }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Makazi') }}</dt><dd class="text-right font-medium">{{ $member->address ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Alijiunga') }}</dt><dd class="font-medium">{{ $member->joined_at?->format('d/m/Y') }}</dd></div>
                </dl>
            </x-card>

            <x-card :title="__('Uanachama')">
                @if ($member->status === 'active')
                    <p class="text-sm text-slate-600">
                        @if ($unpaidMonths)
                            {!! __('Hajalipa ada ya miezi :count iliyopita.', ['count' => '<span class="font-semibold '.($unpaidMonths >= $graceMonths - 1 ? 'text-rose-600' : 'text-slate-900').'">'.$unpaidMonths.'</span>']) !!}
                            {{ __('Akifika miezi :grace atakuwa si mwanachama hai.', ['grace' => $graceMonths]) }}
                        @else
                            {{ __('Hana deni la miezi iliyopita. Ana haki zote za mwanachama.') }}
                        @endif
                    </p>
                    @if ($graceMonths)
                        <div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $unpaidMonths >= $graceMonths - 1 ? 'bg-rose-500' : 'bg-amber-400' }}" style="width: {{ min(100, $unpaidMonths / $graceMonths * 100) }}%"></div></div>
                    @endif
                @elseif (in_array($member->status, ['pending', 'inactive'], true))
                    <p class="text-sm text-slate-600">
                        {{ $member->status === 'pending' ? __('Atakuwa hai akitimiza:') : __('Hana haki za mwanachama (USSD, kusaidiwa kwenye michango). Ili arejee anahitaji:') }}
                    </p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($missing as $item)
                            <li class="flex items-start gap-2 text-sm"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($member->status === 'inactive' && in_array(__('Kurejea hakujaanzishwa'), $missing, true))
                            @can('manage-membership')
                                <form method="POST" action="{{ route('members.reinstatement', $member) }}">
                                    @csrf <x-button size="sm" icon="check">{{ __('Anzisha kurejea') }}</x-button>
                                </form>
                            @endcan
                        @elseif ($fees->contains(fn ($row) => $row['paid'] < $row['fee']->amount_due))
                            @can('manage-finance')
                                <x-button size="sm" :href="route('payments.create', ['member' => $member->id, 'target' => 'membership_fee'])" icon="plus">{{ __('Rekodi kiingilio') }}</x-button>
                            @endcan
                        @endif
                        @if ($member->status === 'inactive')
                            @can('manage-finance')
                                <x-button size="sm" variant="secondary" :href="route('payments.create', ['member' => $member->id, 'target' => 'dues'])">{{ __('Rekodi ada') }}</x-button>
                            @endcan
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-600">{{ __(MemberController::STATUSES[$member->status] ?? $member->status) }}. {{ __('Kiongozi anaweza kumrejesha kupitia "Hariri".') }}</p>
                @endif

                @if ($fees->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Viingilio') }}</p>
                        @foreach ($fees as $row)
                            <div class="flex items-center justify-between py-1.5 text-sm">
                                <span>{{ __($row['fee']->label()) }} <span class="text-xs text-slate-500">· {{ $row['fee']->created_at->format('d/m/Y') }}</span></span>
                                <span class="flex items-center gap-2">{{ Money::format($row['fee']->amount_due) }}
                                    <x-status-badge :status="$row['paid'] >= $row['fee']->amount_due ? 'paid' : ($row['paid'] > 0 ? 'partial' : 'unpaid')" /></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @can('manage-membership')
            <x-card :title="__('PIN ya USSD')">
                <p class="mb-4 text-sm text-slate-500">
                    @if ($member->pin_locked_until?->isFuture())
                        <span class="font-medium text-rose-600">{{ __('PIN imefungwa hadi :time.', ['time' => $member->pin_locked_until->format('H:i')]) }}</span> {{ __('Kuweka PIN mpya kutaifungua.') }}
                    @elseif ($member->pin_hash)
                        {{ __('Mwanachama ana PIN. Weka mpya ikiwa ameisahau.') }}
                    @else
                        {{ __('Mwanachama hana PIN bado, kwa hiyo hawezi kutumia USSD.') }}
                    @endif
                </p>
                <form method="POST" action="{{ route('members.pin', $member) }}" class="grid grid-cols-2 gap-3">
                    @csrf @method('PUT')
                    <x-input :label="__('PIN mpya')" name="pin" type="password" inputmode="numeric" maxlength="4" placeholder="••••" />
                    <x-input :label="__('Rudia PIN')" name="pin_confirmation" type="password" inputmode="numeric" maxlength="4" placeholder="••••" />
                    <div class="col-span-2"><x-button variant="secondary" icon="key" class="w-full">{{ __('Weka PIN') }}</x-button></div>
                </form>
            </x-card>
            @endcan

            <x-card :title="__('Wategemezi')">
                <ul class="divide-y divide-slate-100">
                    @forelse ($member->dependants->where('status', 'active') as $dependant)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span><span class="font-medium text-slate-900">{{ $dependant->name }}</span> <span class="text-slate-500">· {{ __(DependantController::RELATIONSHIPS[$dependant->relationship] ?? $dependant->relationship) }}</span></span>
                            @can('manage-membership')
                                <form method="POST" action="{{ route('members.dependants.destroy', [$member, $dependant]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-slate-400 hover:text-rose-600">{{ __('Ondoa') }}</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="py-2 text-sm text-slate-500">{{ __('Hakuna mtegemezi aliyesajiliwa.') }}</li>
                    @endforelse
                </ul>
                @can('manage-membership')
                    <form method="POST" action="{{ route('members.dependants.store', $member) }}" class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                        @csrf
                        <x-input :label="__('Jina')" name="name" />
                        <x-select :label="__('Uhusiano')" name="relationship" :options="DependantController::RELATIONSHIPS" />
                        <div class="col-span-2"><x-button variant="secondary" icon="plus" class="w-full">{{ __('Ongeza mtegemezi') }}</x-button></div>
                    </form>
                @endcan
            </x-card>

            @can('manage-membership')
                <x-card :title="__('Futa mwanachama')" class="border-rose-200">
                    @if ($canDelete)
                        <p class="text-sm text-slate-600">{{ __('Kwa aliyesajiliwa kwa makosa. Taarifa zake zote zitafutwa na haziwezi kurudishwa.') }}</p>
                        <form method="POST" action="{{ route('members.destroy', $member) }}" class="mt-4"
                            onsubmit="return confirm(@js(__('Futa :name kabisa? Hili haliwezi kurudishwa.', ['name' => $member->full_name])))">
                            @csrf @method('DELETE')
                            <x-button variant="danger" class="w-full">{{ __('Futa mwanachama') }}</x-button>
                        </form>
                    @else
                        <p class="text-sm text-slate-600">{{ __('Ana malipo yaliyorekodiwa, kwa hiyo hawezi kufutwa bila kuharibu hesabu za kikundi. Ameacha kikundi? Badilisha hali yake kuwa "Ameondoka" kupitia Hariri.') }}</p>
                        <x-button variant="secondary" :href="route('members.edit', $member)" class="mt-4 w-full">{{ __('Hariri hali') }}</x-button>
                    @endif
                </x-card>
            @endcan
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-stat :label="__('Deni la ada')" :value="Money::format($outstanding)" icon="calendar" :tone="$outstanding ? 'rose' : 'emerald'" :hint="$outstanding ? __('Linadaiwa') : __('Hana deni')" />
                <x-stat :label="__('Akiba yake')" :value="Money::format($savings)" icon="wallet" tone="violet" :hint="__('Kutoka malipo yaliyothibitishwa')" />
            </div>

            <x-card :title="__('Ada kwa mwezi')" :padded="false">
                @if ($obligations->isEmpty())
                    <x-empty icon="calendar" :title="__('Hakuna ada iliyopangwa')" :text="__('Zalisha madeni ya mwezi kwenye ukurasa wa Ada.')" />
                @else
                    <x-table>
                        <x-slot:head><tr><th class="px-5 py-3">{{ __('Mwezi') }}</th><th class="px-5 py-3 text-right">{{ __('Inadaiwa') }}</th><th class="px-5 py-3 text-right">{{ __('Imelipwa') }}</th><th class="px-5 py-3">{{ __('Hali') }}</th></tr></x-slot:head>
                        @foreach ($obligations as $row)
                            @php $o = $row['obligation']; $state = $row['paid'] >= $o->amount_due ? 'paid' : ($row['paid'] > 0 ? 'partial' : 'unpaid'); @endphp
                            <tr>
                                <td class="px-5 py-3 font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $o->period)->translatedFormat('F Y') }}</td>
                                <td class="px-5 py-3 text-right">{{ Money::format($o->amount_due) }}</td>
                                <td class="px-5 py-3 text-right">{{ Money::format($row['paid']) }}</td>
                                <td class="px-5 py-3"><x-status-badge :status="$state" /></td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <x-card :title="__('Malipo ya karibuni')" :padded="false">
                @if ($payments->isEmpty())
                    <x-empty icon="banknotes" :title="__('Hakuna malipo bado')" />
                @else
                    <x-table>
                        <x-slot:head><tr><th class="px-5 py-3">{{ __('Kumbukumbu') }}</th><th class="px-5 py-3">{{ __('Aina') }}</th><th class="px-5 py-3 text-right">{{ __('Kiasi') }}</th><th class="px-5 py-3">{{ __('Hali') }}</th></tr></x-slot:head>
                        @foreach ($payments as $p)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-mono text-xs font-medium">{{ $p->reference }}</p>
                                    <p class="text-xs text-slate-500">{{ $p->created_at->format('d/m/Y H:i') }}
                                        @if ($p->payer_member_id !== $p->credited_member_id)
                                            · {{ $p->payer_member_id === $member->id ? __('Kwa :name', ['name' => $p->creditedMember->full_name]) : __('Kutoka :name', ['name' => $p->payer->full_name]) }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3">{{ __(['dues' => 'Ada', 'savings' => 'Akiba', 'event' => 'Mchango', 'membership_fee' => 'Kiingilio'][$p->target_type] ?? $p->target_type) }}</td>
                                <td class="px-5 py-3 text-right font-medium">{{ Money::format($p->amount) }}</td>
                                <td class="px-5 py-3"><x-status-badge :status="$p->status" /></td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <x-card :title="__('Historia ya uanachama')">
                <ol class="relative space-y-5 border-l border-slate-200 pl-5">
                    @forelse ($member->statusChanges as $change)
                        <li class="relative">
                            <span class="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-white {{ ['active' => 'bg-emerald-500', 'inactive' => 'bg-rose-500', 'pending' => 'bg-amber-400'][$change->to_status] ?? 'bg-slate-400' }}"></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$change->to_status" />
                                <span class="text-xs text-slate-500">{{ $change->created_at->format('d/m/Y H:i') }} · {{ $change->changedBy?->name ?? __('Mfumo') }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ __($change->reason, $change->reason_params ?? []) }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">{{ __('Hakuna mabadiliko yaliyorekodiwa bado.') }}</li>
                    @endforelse
                </ol>
            </x-card>
        </div>
    </div>
@endsection
