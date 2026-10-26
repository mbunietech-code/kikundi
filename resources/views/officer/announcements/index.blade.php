@extends('layouts.app')
@section('title', __('Matangazo'))

@section('content')
    <x-page-header :title="__('Matangazo na SMS')" :subtitle="__('Tangazo lililochapishwa linaonekana kwenye USSD (6. Matangazo). Unaweza pia kulituma kwa SMS.')" />

    <div class="grid gap-6 xl:grid-cols-3">
        @can('manage-membership')
        <x-card :title="__('Tangazo jipya')" x-data="{ body: @js(old('body', '')) }">
            <form method="POST" action="{{ route('announcements.store') }}" class="space-y-4">
                @csrf
                <x-input :label="__('Kichwa')" name="title" maxlength="60" required :placeholder="__('Mfano: Kikao cha mwezi')" />
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ __('Ujumbe') }}</span>
                    <textarea name="body" rows="4" maxlength="300" x-model="body" required class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600"></textarea>
                    <span class="mt-1 flex justify-between text-xs text-slate-500"><span>{{ __('SMS moja ni herufi 160') }}</span><span x-text="body.length + ' / 300'"></span></span>
                    @error('body') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <x-input :label="__('Mwisho wa kuonekana (si lazima)')" name="expires_at" type="date" />
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="publish" value="1" checked class="rounded border-slate-300 text-emerald-600"> {{ __('Chapisha sasa') }}</label>
                <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="send_sms" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600"> <span>{{ __('Tuma SMS kwa wanachama hai :count', ['count' => $recipients]) }}<br><span class="text-xs text-slate-500">{{ __('Kila SMS ina gharama ya Beem.') }}</span></span></label>
                <x-button class="w-full" icon="megaphone">{{ __('Hifadhi tangazo') }}</x-button>
            </form>
        </x-card>
        @endcan

        <x-card :title="__('Matangazo yote')" :padded="false" :class="auth()->user()->can('manage-membership') ? 'xl:col-span-2' : 'xl:col-span-3'">
            @forelse ($announcements as $a)
                <article class="border-b border-slate-100 px-5 py-4 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-slate-900">{{ $a->title }}</h3>
                                <x-status-badge :status="$a->status" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $a->body }}</p>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ $a->published_at ? __('Lilichapishwa :date', ['date' => $a->published_at->format('d/m/Y H:i')]) : __('Liliundwa :date', ['date' => $a->created_at->format('d/m/Y')]) }}
                                @if ($a->expires_at) · {{ __('Mwisho :date', ['date' => $a->expires_at->format('d/m/Y')]) }} @endif
                            </p>
                        </div>
                        @can('manage-membership')
                        <div class="flex gap-2">
                            @if ($a->status === 'draft')
                                <form method="POST" action="{{ route('announcements.publish', $a) }}">@csrf <x-button size="sm" variant="secondary">{{ __('Chapisha') }}</x-button></form>
                            @endif
                            <form method="POST" action="{{ route('announcements.sms', $a) }}" onsubmit="return confirm(@js(__('Tuma SMS kwa wanachama hai :count?', ['count' => $recipients])))">@csrf <x-button size="sm" variant="ghost" icon="phone">{{ __('Tuma SMS') }}</x-button></form>
                        </div>
                        @endcan
                    </div>
                </article>
            @empty
                <x-empty icon="megaphone" :title="__('Hakuna tangazo bado')" />
            @endforelse
            {{ $announcements->links() }}
        </x-card>
    </div>
@endsection
