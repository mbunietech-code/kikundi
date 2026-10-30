@extends('layouts.app')
@section('title', __('Malipo'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Officer\PaymentController;
    $tabs = ['pending' => 'Yanasubiri', 'succeeded' => 'Yamethibitishwa', 'failed' => 'Yamekataliwa'];
@endphp

@section('content')
    <x-page-header :title="__('Malipo')" :subtitle="__('Thibitisha maombi ya USSD dhidi ya taarifa rasmi kabla salio halijabadilika.')">
        <x-slot:actions>
            @can('manage-finance')
                <x-button :href="route('payments.create')" icon="plus">{{ __('Rekodi malipo ya ofisini') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('payments.index', ['status' => $key]) }}"
                class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium {{ $status === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                {{ __($label) }} <span class="rounded-full px-1.5 text-xs {{ $status === $key ? 'bg-white/20' : 'bg-slate-100' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
        <form method="GET" class="ml-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Tafuta kumbukumbu') }}" class="rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
        </form>
    </div>

    <x-card :padded="false">
        @if ($payments->isEmpty())
            <x-empty icon="banknotes" :title="$status === 'pending' ? __('Hakuna malipo yanayosubiri') : __('Hakuna malipo hapa')" :text="__('Maombi ya malipo kutoka USSD huanza kama “Inasubiri” hadi mweka hazina ayathibitishe.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <th class="px-5 py-3">{{ __('Kumbukumbu') }}</th>
                        <th class="px-5 py-3">{{ __('Mwanachama') }}</th>
                        <th class="hidden px-5 py-3 md:table-cell">{{ __('Kwa ajili ya') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Kiasi') }}</th>
                        <th class="px-5 py-3">{{ $status === 'pending' ? __('Kitendo') : __('Hali') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($payments as $p)
                    <tr x-data="{ open: false }" class="align-top">
                        <td class="px-5 py-3">
                            <p class="font-mono text-xs font-semibold">{{ $p->reference }}</p>
                            <p class="text-xs text-slate-500">{{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->channel === 'ussd' ? 'USSD' : __('Ofisini') }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ $p->creditedMember->full_name }}</p>
                            <p class="text-xs text-slate-500">
                                @if ($p->payer_member_id !== $p->credited_member_id) {{ __('Amelipiwa na :name', ['name' => $p->payer->full_name]) }} · @endif
                                +{{ $p->payer_msisdn ?? $p->payer->phone }}
                            </p>
                        </td>
                        <td class="hidden px-5 py-3 md:table-cell">{{ $labels[$p->id] ?? '' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-semibold">{{ Money::format($p->amount) }}</td>
                        <td class="px-5 py-3">
                            @if ($p->status === 'pending' && auth()->user()->can('manage-finance'))
                                <div class="flex gap-2">
                                    <x-button size="sm" type="button" @click="open = !open" icon="check">{{ __('Thibitisha') }}</x-button>
                                    <form method="POST" action="{{ route('payments.reject', $p) }}" onsubmit="return confirm(@js(__('Kataa ombi hili? Hakuna salio litakalobadilika.')))">
                                        @csrf <x-button size="sm" variant="ghost">{{ __('Kataa') }}</x-button>
                                    </form>
                                </div>
                                <form x-show="open" x-cloak method="POST" action="{{ route('payments.verify', $p) }}" class="mt-3 w-64 space-y-2 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
                                    @csrf
                                    <p class="text-xs text-slate-600">{{ __('Hakiki :amount kwenye taarifa rasmi kwanza.', ['amount' => Money::format($p->amount)]) }}</p>
                                    <select name="method" class="w-full rounded-lg border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
                                        @foreach (PaymentController::METHODS as $k => $v) <option value="{{ $k }}" @selected($k === 'mpesa')>{{ __($v) }}</option> @endforeach
                                    </select>
                                    <input name="transaction_id" required placeholder="{{ __('Namba ya muamala') }}" class="w-full rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
                                    <x-button size="sm" class="w-full">{{ __('Thibitisha malipo') }}</x-button>
                                </form>
                            @else
                                <x-status-badge :status="$p->status" />
                            @endif
                            @can('manage-system')
                                {{-- Administrators only; the reason goes to the deletion log. --}}
                                <div x-data="{ remove: false }" class="mt-2">
                                    <button type="button" @click="remove = !remove" class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-rose-600">
                                        <x-icon name="trash" class="h-3.5 w-3.5" /> {{ __('Futa') }}
                                    </button>
                                    <form x-show="remove" x-cloak method="POST" action="{{ route('payments.destroy', $p) }}" class="mt-2 w-64 space-y-2 rounded-xl bg-rose-50 p-3 ring-1 ring-rose-200"
                                        onsubmit="return confirm(@js(__('Futa malipo :reference ya :amount kabisa? Salio na ripoti zitabadilika.', ['reference' => $p->reference, 'amount' => Money::format($p->amount)])))">
                                        @csrf @method('DELETE')
                                        <p class="text-xs text-rose-800">{{ __('Kwa malipo yaliyowekwa kwa makosa. Yataandikwa kwenye kumbukumbu za kufuta.') }}</p>
                                        <input name="reason" required minlength="5" maxlength="190" placeholder="{{ __('Sababu ya kufuta') }}" class="w-full rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-rose-300 focus:ring-2 focus:ring-rose-500">
                                        <x-button size="sm" variant="danger" class="w-full">{{ __('Futa malipo') }}</x-button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
            {{ $payments->links() }}
        @endif
    </x-card>
@endsection
