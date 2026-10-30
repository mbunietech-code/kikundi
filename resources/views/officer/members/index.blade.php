@extends('layouts.app')
@section('title', __('Wanachama'))

@php
    $tabs = ['active' => __('Hai'), 'inactive' => __('Sio hai'), 'pending' => __('Wanasubiri kiingilio'), 'suspended' => __('Wamesimamishwa'), 'departed' => __('Wameondoka'), 'all' => __('Wote')];
@endphp

@section('content')
    <x-page-header :title="__('Wanachama')" :subtitle="__('Usajili wa wanachama, simu zao na hali ya uanachama.')">
        <x-slot:actions>
            @can('manage-membership')
                <form method="POST" action="{{ route('members.enforce') }}" onsubmit="return confirm(@js(__('Kagua sasa? Wanaodaiwa zaidi ya muda wa Katiba watakuwa si hai na watapata SMS.')))">
                    @csrf <x-button variant="secondary" icon="shield">{{ __('Kagua hali') }}</x-button>
                </form>
                <x-button :href="route('members.create')" icon="user-plus">{{ __('Ongeza mwanachama') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="flex w-max gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200">
            @foreach ($tabs as $key => $label)
                @php $count = $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0); @endphp
                <a href="{{ route('members.index', array_filter(['status' => $key, 'q' => request('q')])) }}"
                    class="flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $status === $key ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 text-xs {{ $status === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ $count }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <x-card :padded="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <input name="q" value="{{ request('q') }}" placeholder="{{ __('Tafuta kwa jina, namba au simu') }}"
                    class="w-full rounded-lg border-0 py-2 pl-9 pr-3 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
            </div>
            <x-button variant="secondary">{{ __('Tafuta') }}</x-button>
        </form>

        @if ($members->isEmpty())
            <x-empty icon="users" :title="__('Hakuna mwanachama aliyepatikana')" :text="$status === 'inactive' ? __('Hakuna mwanachama aliyepoteza uhai wa uanachama.') : __('Badilisha utafutaji au chagua kundi lingine.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <th class="px-5 py-3">{{ __('Mwanachama') }}</th>
                        <th class="px-5 py-3">{{ __('Simu') }}</th>
                        <th class="hidden px-5 py-3 md:table-cell">{{ __('Alijiunga') }}</th>
                        <th class="hidden px-5 py-3 sm:table-cell">{{ __('Wategemezi') }}</th>
                        <th class="hidden px-5 py-3 sm:table-cell">USSD</th>
                        <th class="px-5 py-3">{{ __('Hali') }}</th>
                        @can('manage-membership') <th class="px-5 py-3"><span class="sr-only">{{ __('Futa mwanachama') }}</span></th> @endcan
                    </tr>
                </x-slot:head>
                @foreach ($members as $member)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="location='{{ route('members.show', $member) }}'">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <x-avatar :member="$member" />
                                <div>
                                    <p class="font-medium text-slate-900">{{ $member->full_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $member->member_number }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">+{{ $member->phone }}</td>
                        <td class="hidden whitespace-nowrap px-5 py-3 md:table-cell">{{ $member->joined_at?->format('d/m/Y') }}</td>
                        <td class="hidden px-5 py-3 sm:table-cell">{{ $member->dependants_count }}</td>
                        <td class="hidden px-5 py-3 sm:table-cell">
                            @if ($member->pin_hash) <x-badge tone="emerald">{{ __('PIN ipo') }}</x-badge> @else <x-badge tone="amber">{{ __('Hana PIN') }}</x-badge> @endif
                        </td>
                        <td class="px-5 py-3">
                            <x-status-badge :status="$member->status" />
                            @if (! $member->isActive() && $member->status_changed_at)
                                <p class="mt-1 text-xs text-slate-500">{{ $member->status_changed_at->diffForHumans() }}</p>
                            @endif
                        </td>
                        @can('manage-membership')
                            {{-- Clicks here must not open the member, which the whole row does. --}}
                            <td class="px-3 py-3 text-right" onclick="event.stopPropagation()">
                                @if (in_array($member->id, $withPayments, true))
                                    <span class="inline-flex cursor-not-allowed rounded-lg p-2 text-slate-300" title="{{ __('Ana malipo yaliyorekodiwa, kwa hiyo hawezi kufutwa bila kuharibu hesabu za kikundi. Ameacha kikundi? Badilisha hali yake kuwa "Ameondoka" kupitia Hariri.') }}">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('members.destroy', $member) }}"
                                        onsubmit="return confirm(@js(__('Futa :name kabisa? Hili haliwezi kurudishwa.', ['name' => $member->full_name])))">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" title="{{ __('Futa mwanachama') }}" aria-label="{{ __('Futa mwanachama') }}: {{ $member->full_name }}">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @endforeach
            </x-table>
            {{ $members->links() }}
        @endif
    </x-card>
@endsection
