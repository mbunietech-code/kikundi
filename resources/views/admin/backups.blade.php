@extends('layouts.app')
@section('title', __('Nakala za akiba'))

@section('content')
    <x-page-header :title="__('Nakala za akiba (backup)')" :subtitle="__('Database yote na picha za wanachama. Hutengenezwa kila siku saa 7 usiku.')">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf <x-button icon="plus">{{ __('Tengeneza sasa') }}</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card :title="__('Nakala zilizopo')" :padded="false" class="xl:col-span-2">
            @if ($backups->isEmpty())
                <x-empty icon="shield" :title="__('Bado hakuna nakala')" :text="__('Bonyeza “Tengeneza sasa” kupata nakala ya kwanza.')" />
            @else
                <x-table>
                    <x-slot:head><tr><th class="px-5 py-3">{{ __('Faili') }}</th><th class="px-5 py-3">{{ __('Tarehe') }}</th><th class="px-5 py-3 text-right">{{ __('Ukubwa') }}</th><th class="px-5 py-3"></th></tr></x-slot:head>
                    @foreach ($backups as $b)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs">{{ $b['name'] }} @if ($loop->first) <x-badge tone="emerald" class="ml-1">{{ __('Mpya') }}</x-badge> @endif</td>
                            <td class="whitespace-nowrap px-5 py-3">{{ $b['created_at']->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                            <td class="px-5 py-3 text-right"><x-button size="sm" variant="secondary" icon="download" :href="route('admin.backups.download', $b['name'])">{{ __('Pakua') }}</x-button></td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card :title="__('Muhimu')">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li>{{ __('Nakala zinahifadhiwa kwenye kompyuta hii. Kompyuta ikiharibika, zinapotea pamoja nayo.') }} <span class="font-medium text-slate-900">{{ __('Pakua nakala angalau kila wiki') }}</span> {{ __('na uiweke kwenye flash, Google Drive au sehemu nyingine salama.') }}</li>
                    <li>{{ __('Nakala za zaidi ya siku :days zinafutwa zenyewe. Ya karibuni hubaki daima.', ['days' => $keepDays]) }} {{ __('Badilisha idadi ya siku kwenye') }} <a href="{{ route('admin.settings.edit') }}" class="font-medium text-emerald-700 hover:underline">{{ __('Mipangilio') }}</a>.</li>
                    <li>{{ __('Nakala ina taarifa binafsi za wanachama. Usiitume kwa mtu asiye kiongozi.') }}</li>
                </ul>
            </x-card>
            <x-card :title="__('Kurejesha')">
                <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-600">
                    <li>{{ __('Fungua faili ya ZIP.') }}</li>
                    <li>{!! __('Ingiza :file kwenye database kwa phpMyAdmin (Import).', ['file' => '<span class="font-mono text-xs">database.sql</span>']) !!}</li>
                    <li>{!! __('Nakili folda :folder kwenda :path.', ['folder' => '<span class="font-mono text-xs">member-photos</span>', 'path' => '<span class="font-mono text-xs">storage/app/private/</span>']) !!}</li>
                </ol>
                <p class="mt-3 text-xs text-slate-500">{{ __('Maelezo haya yako pia ndani ya kila ZIP (README.txt).') }}</p>
            </x-card>
        </div>
    </div>
@endsection
