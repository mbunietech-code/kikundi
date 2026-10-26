@extends('layouts.app')
@section('title', $event->exists ? __('Hariri mchango') : __('Mchango mpya'))

@section('content')
    <x-page-header :title="$event->exists ? __('Hariri mchango') : __('Mchango mpya')" :subtitle="__('Weka taarifa binafsi kwa ufupi tu. Jina la mchango linaonekana kwenye USSD na kwenye SMS za ukumbusho.')" :back="$event->exists ? route('events.show', $event) : route('events.index')" />

    <form method="POST" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}" class="max-w-3xl">
        @csrf
        @if ($event->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select :label="__('Aina')" name="event_type" :options="$types" :value="$event->event_type" />
                <x-select :label="__('Hali')" name="status" :options="$statuses" :value="$event->status" />
                <div class="sm:col-span-2"><x-input :label="__('Jina la mchango')" name="title" :value="$event->title" maxlength="60" :placeholder="__('Mfano: Matibabu ya Mzee Hamisi')" required :hint="__('Herufi 60 au chache, ili lisomeke vizuri kwenye USSD.')" /></div>
                <x-select :label="__('Mnufaika (si lazima)')" name="beneficiary_member_id" :options="$members" :value="$event->beneficiary_member_id" placeholder="Hakuna / si mwanachama" />
                <div></div>
                <x-input :label="__('Kiasi kwa kila mwanachama (TSh)')" name="amount_per_member" type="number" min="1" :value="$event->amount_per_member"
                    :hint="__('Ukijaza, mfumo unaonyesha anayedaiwa na kutuma ukumbusho. Acha wazi kama ni kwa hiari.')" />
                <x-input :label="__('Lengo la jumla (TSh, si lazima)')" name="target_amount" type="number" min="1" :value="$event->target_amount" />
                <x-input :label="__('Tarehe ya kuanza')" name="starts_at" type="date" :value="($event->starts_at ?? now())->format('Y-m-d')" required />
                <x-input :label="__('Tarehe ya mwisho')" name="ends_at" type="date" :value="$event->ends_at?->format('Y-m-d')" :hint="__('Ukumbusho wa SMS hutumwa siku chache kabla.')" />
                <div class="sm:col-span-2"><x-textarea :label="__('Maelezo')" name="description" :value="$event->description" rows="3" /></div>
            </div>
            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <x-button variant="secondary" :href="route('events.index')">{{ __('Ghairi') }}</x-button>
                <x-button icon="check">{{ __('Hifadhi') }}</x-button>
            </div>
        </x-card>
    </form>
@endsection
