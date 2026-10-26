@extends('layouts.app')
@section('title', __('Rekodi malipo'))

@php use App\Http\Controllers\Officer\PaymentController; @endphp

@section('content')
    <x-page-header :title="__('Rekodi malipo ya ofisini')" :subtitle="__('Tumia hii kwa pesa ambayo tayari umeiona kwenye taarifa rasmi (taslimu, simu au benki).')" :back="route('payments.index')" />

    <form method="POST" action="{{ route('payments.store') }}" class="max-w-3xl"
        x-data="{ target: '{{ old('target_type', request('target', 'dues')) }}' }">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-select :label="__('Mwanachama')" name="credited_member_id" :options="$members" :value="request('member')" placeholder="Chagua mwanachama" required />
                </div>

                <div class="sm:col-span-2">
                    <span class="text-sm font-medium text-slate-700">{{ __('Aina ya malipo') }}</span>
                    <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach (PaymentController::TARGETS as $key => $label)
                            <label class="cursor-pointer rounded-lg px-3 py-2 text-center text-sm font-medium ring-1 ring-inset transition"
                                :class="target === '{{ $key }}' ? 'bg-emerald-50 text-emerald-800 ring-emerald-500' : 'text-slate-600 ring-slate-300 hover:bg-slate-50'">
                                <input type="radio" name="target_type" value="{{ $key }}" x-model="target" class="sr-only"> {{ __($label) }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="sm:col-span-2" x-show="target === 'dues'">
                    <x-select :label="__('Mwezi wa ada')" name="obligation_id" :options="$obligations" placeholder="Chagua mwezi unaodaiwa" />
                </div>
                <div class="sm:col-span-2" x-show="target === 'event'" x-cloak>
                    <x-select :label="__('Mchango')" name="event_id" :options="$events" :value="request('event')" placeholder="Chagua mchango" />
                </div>
                <div class="sm:col-span-2" x-show="target === 'membership_fee'" x-cloak>
                    <x-select :label="__('Kiingilio')" name="fee_id" :options="$fees" placeholder="Chagua kiingilio kinachodaiwa" />
                    <p class="mt-1 text-xs text-slate-500">{{ $fees ? __('Mwanachama atakuwa hai mwenyewe akitimiza masharti.') : __('Hakuna kiingilio kinachodaiwa kwa sasa.') }}</p>
                </div>

                <x-input :label="__('Kiasi (TSh)')" name="amount" type="number" min="1" required />
                <x-select :label="__('Njia ya malipo')" name="method" :options="$methods" value="cash" />
                <div class="sm:col-span-2">
                    <x-input :label="__('Namba ya muamala / risiti')" name="transaction_id" required :hint="__('Kwa taslimu, tumia namba ya risiti ya kitabu. Namba moja haiwezi kurekodiwa mara mbili.')" />
                </div>
            </div>
            <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
                <p class="text-xs text-slate-500">{{ __('Mwanachama atapata SMS ya risiti.') }}</p>
                <x-button icon="check">{{ __('Rekodi malipo') }}</x-button>
            </div>
        </x-card>
    </form>
@endsection
