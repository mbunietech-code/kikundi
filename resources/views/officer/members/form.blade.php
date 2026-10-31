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
                <x-input :label="__('Simu')" name="phone" :value="\App\Support\Phone::display($member->phone) ?: null" placeholder="0712345678" :hint="__('Tanzania: 07XXXXXXXX. Nje ya nchi: anza na + na msimbo wa nchi, mfano +86 138 0013 8000.')" required />
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
            @php
                $oldRows = old('guarantors', []);
                $rowCount = max(1, $requiredGuarantors, count($oldRows));
            @endphp
            <x-card :title="__('Wadhamini')">
                <p class="text-sm text-slate-600">
                    {{ $requiredGuarantors ? __('Katiba inahitaji wadhamini :count kwa mwanachama mpya.', ['count' => $requiredGuarantors]) : __('Si lazima. Weka wanaomdhamini mwanachama huyu kujiunga na kikundi.') }}
                    {{ __('Chagua mwanachama, au kwa mtu wa nje andika jina na simu.') }}
                </p>
                @if ($errors->has('guarantors') || $errors->has('guarantors.*'))
                    <ul class="mt-3 space-y-1 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">
                        @foreach (array_unique(array_merge($errors->get('guarantors'), ...array_values($errors->get('guarantors.*')))) as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4 space-y-3" x-data="{ shown: {{ $rowCount }}, members: @js(array_map(fn ($row) => (string) ($row['member_id'] ?? ''), array_pad($oldRows, \App\Http\Controllers\Officer\GuarantorController::MAX, []))) }">
                    @for ($i = 0; $i < \App\Http\Controllers\Officer\GuarantorController::MAX; $i++)
                        <div x-show="shown > {{ $i }}" @if ($i >= $rowCount) x-cloak @endif class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">{{ __('Mdhamini :number', ['number' => $i + 1]) }}</p>
                            <label class="block sm:col-span-2">
                                <span class="text-sm font-medium text-slate-700">{{ __('Mwanachama') }}</span>
                                <select name="guarantors[{{ $i }}][member_id]" x-model="members[{{ $i }}]" class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                                    <option value="">{{ __('— Si mwanachama (mtu wa nje) —') }}</option>
                                    @foreach ($guarantorOptions as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old("guarantors.$i.member_id") === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <template x-if="! members[{{ $i }}]">
                                <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                                    <x-input :label="__('Jina la mdhamini')" name="guarantors[{{ $i }}][name]" :value="old('guarantors.'.$i.'.name')" />
                                    <x-input :label="__('Simu ya mdhamini')" name="guarantors[{{ $i }}][phone]" :value="old('guarantors.'.$i.'.phone')" placeholder="0712345678" inputmode="tel" />
                                </div>
                            </template>
                            <div class="sm:col-span-2"><x-input :label="__('Uhusiano (si lazima)')" name="guarantors[{{ $i }}][relationship]" :value="old('guarantors.'.$i.'.relationship')" :placeholder="__('Mfano: rafiki, ndugu, jirani')" /></div>
                        </div>
                    @endfor
                    <button type="button" x-show="shown < {{ \App\Http\Controllers\Officer\GuarantorController::MAX }}" @click="shown++" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700 hover:underline">
                        <x-icon name="plus" class="h-4 w-4" /> {{ __('Ongeza mdhamini') }}
                    </button>
                </div>
            </x-card>

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
