@extends('layouts.app')
@section('title', $member->exists ? __('Hariri mwanachama') : __('Mwanachama mpya'))

@php use App\Support\Money; @endphp

@section('content')
    <x-page-header :title="$member->exists ? __('Hariri: :name', ['name' => $member->full_name]) : __('Mwanachama mpya')"
        :subtitle="__('Hakiki taarifa kabla ya kuhifadhi. Namba ya simu inatumika kuingia USSD.')"
        :back="$member->exists ? route('members.show', $member) : route('members.index')" />

    <form method="POST" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @if ($member->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input :label="__('Namba ya uanachama')" name="member_number" :value="$member->member_number" placeholder="M001" required
                    :hint="$member->exists ? null : __('Imejazwa namba inayofuata. Unaweza kuibadilisha.')" />
                <x-input :label="__('Jina kamili')" name="full_name" :value="$member->full_name" required />
                <x-input :label="__('Simu')" name="phone" :value="$member->phone ? '0'.substr($member->phone, 3) : null" placeholder="0712345678" :hint="__('Itahifadhiwa kama 2557XXXXXXXX')" required />
                <x-input :label="__('Tarehe ya kujiunga')" name="joined_at" type="date" :value="$member->joined_at?->format('Y-m-d')" required />
                <x-input :label="__('Makazi')" name="address" :value="$member->address" :placeholder="__('Mtaa, kata')" />
                <x-select :label="__('Lugha ya SMS na USSD')" name="locale" :value="$member->locale"
                    :options="['' => __('Lugha ya kikundi (:language)', ['language' => \App\Support\Locale::SUPPORTED[\App\Support\Locale::default()]])] + \App\Support\Locale::SUPPORTED" />

                <div class="sm:col-span-2" x-data="{ preview: null }">
                    <span class="text-sm font-medium text-slate-700">{{ __('Picha (hiari)') }}</span>
                    <div class="mt-1.5 flex items-center gap-4">
                        <template x-if="preview"><img :src="preview" alt="" class="h-14 w-14 rounded-full object-cover"></template>
                        <template x-if="!preview">
                            @if ($member->exists)
                                <x-avatar :member="$member" size="h-14 w-14 text-lg" />
                            @else
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon name="users" /></span>
                            @endif
                        </template>
                        <input type="file" name="photo" accept="image/*" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                    @error('photo') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </x-card>

        @unless ($member->exists)
            @if ($entryFee > 0)
                <x-card :title="__('Kiingilio')">
                    <p class="text-sm text-slate-600">{!! __('Kiingilio ni :amount. Mwanachama mpya atakuwa :status hadi kiingilio kilipwe, kisha atakuwa hai mwenyewe.', ['amount' => '<span class="font-semibold text-slate-900">'.e(Money::format($entryFee)).'</span>', 'status' => '<span class="font-semibold text-amber-700">'.e(__('Inasubiri')).'</span>']) !!}</p>
                    <label class="mt-4 flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <input type="checkbox" name="entry_fee_paid" value="1" @checked(old('entry_fee_paid')) class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                        <span><span class="font-medium text-slate-900">{{ __('Mwanachama wa zamani') }}</span><br><span class="text-slate-500">{{ __('Alishalipa kiingilio kabla ya mfumo huu. Atakuwa hai mara moja bila deni la kiingilio.') }}</span></span>
                    </label>
                </x-card>
            @endif
        @else
            <x-card :title="__('Hali ya uanachama')">
                <div class="flex items-center gap-2 text-sm text-slate-600">{{ __('Hali ya sasa:') }} <x-status-badge :status="$member->status" /></div>
                <p class="mt-2 text-sm text-slate-500">{{ __('"Hai", "Sio hai" na "Inasubiri" hubadilika zenyewe kutokana na malipo. Hapa unaweza kumsimamisha au kurekodi kuondoka kwake.') }}</p>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    @php
                        // A suspended or departed member can only be brought back.
                        $actions = array_key_exists($member->status, $statuses)
                            ? ['' => 'Usibadilishe', 'active' => 'Rejesha kuwa hai']
                            : ['' => 'Usibadilishe'] + $statuses;
                    @endphp
                    <x-select :label="__('Badilisha hali')" name="status_action" :options="$actions" />
                    <x-input :label="__('Sababu')" name="status_reason" :placeholder="__('Itaonekana kwenye historia')" />
                </div>
            </x-card>
        @endunless

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="$member->exists ? route('members.show', $member) : route('members.index')">{{ __('Ghairi') }}</x-button>
            <x-button icon="check">{{ __('Hifadhi') }}</x-button>
        </div>
    </form>
@endsection
