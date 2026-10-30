@extends('layouts.app')
@section('title', __('Futa mwanachama'))

@php
    use App\Support\Money;
    use App\Services\Reports\ReportService;
@endphp

@section('content')
    <x-page-header :title="__('Futa :name pamoja na malipo yake', ['name' => $member->full_name])"
        :subtitle="__('Hatua hii haiwezi kurudishwa. Soma yatakayofutwa kabla ya kuendelea.')" :back="route('members.show', $member)" />

    <div class="grid max-w-5xl gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-card :title="__('Kitakachofutwa')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Mwanachama') }}</dt><dd class="font-medium">{{ $member->full_name }} ({{ $member->member_number }})</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Malipo yaliyothibitishwa') }}</dt><dd class="font-medium">{{ $summary['count'] }}</dd></div>
                    @foreach ($summary['by_type'] as $type => $amount)
                        <div class="flex justify-between pl-4"><dt class="text-slate-500">{{ __(ReportService::TARGETS[$type] ?? $type) }}</dt><dd>{{ Money::format($amount) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold"><dt>{{ __('Jumla itakayoondoka kwenye hesabu') }}</dt><dd class="text-rose-600">{{ Money::format($summary['total']) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">{{ __('Pia zitafutwa: wategemezi, ada zisizolipwa, viingilio, historia ya uanachama na picha. Maombi ya malipo yanayosubiri yatafutwa pia.') }}</p>
            </x-card>

            @if ($summary['for_others']->isNotEmpty())
                <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
                    <p class="font-semibold text-amber-900">{{ __('Malipo aliyolipia wanachama wengine') }}</p>
                    <p class="mt-1 text-sm text-amber-900">{{ __('Haya yatafutwa pia, na madeni au akiba ya wanachama hawa yatabadilika:') }}</p>
                    <ul class="mt-3 space-y-1 text-sm text-amber-900">
                        @foreach ($summary['for_others'] as $p)
                            <li>• {{ $p->creditedMember->full_name }}: {{ __(ReportService::TARGETS[$p->target_type] ?? $p->target_type) }} {{ Money::format($p->amount) }} <span class="font-mono text-xs">({{ $p->reference }})</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($summary['intents']->isNotEmpty())
                <x-card :title="__('Malipo yote')" :padded="false">
                    <x-table>
                        <x-slot:head><tr><th class="px-5 py-3">{{ __('Kumbukumbu') }}</th><th class="px-5 py-3">{{ __('Aina') }}</th><th class="px-5 py-3 text-right">{{ __('Kiasi') }}</th><th class="px-5 py-3">{{ __('Hali') }}</th></tr></x-slot:head>
                        @foreach ($summary['intents'] as $p)
                            <tr>
                                <td class="px-5 py-2.5"><span class="font-mono text-xs">{{ $p->reference }}</span><br><span class="text-xs text-slate-500">{{ $p->created_at->format('d/m/Y') }}</span></td>
                                <td class="px-5 py-2.5">{{ __(ReportService::TARGETS[$p->target_type] ?? $p->target_type) }}</td>
                                <td class="px-5 py-2.5 text-right">{{ Money::format($p->amount) }}</td>
                                <td class="px-5 py-2.5"><x-status-badge :status="$p->status" /></td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif
        </div>

        <div class="lg:col-span-2">
            <x-card :title="__('Thibitisha')" class="border-rose-200">
                <form method="POST" action="{{ route('members.destroy-with-payments', $member) }}" class="space-y-4"
                    onsubmit="return confirm(@js(__('Futa :name na malipo yake yote kabisa?', ['name' => $member->full_name])))">
                    @csrf @method('DELETE')
                    <x-input :label="__('Sababu ya kufuta')" name="reason" required minlength="5" maxlength="190" :placeholder="__('Mfano: aliwekwa kwa majaribio')" />
                    <x-input :label="__('Andika namba yake ya uanachama: :number', ['number' => $member->member_number])" name="confirm_number" required autocomplete="off" />
                    <x-button variant="danger" class="w-full">{{ __('Futa kabisa') }}</x-button>
                </form>
                <p class="mt-4 text-xs text-slate-500">{{ __('Kila ufutaji unaandikwa kwenye kumbukumbu za kufuta: nani, lini, kiasi na sababu.') }}</p>
            </x-card>
        </div>
    </div>
@endsection
