@extends('layouts.app')
@section('title', __($types[$type][0]))

@php
    use App\Services\Reports\ReportPeriod;
    $query = $period->query() + array_filter(['member' => $member?->id]);
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F')])->all();
    $years = range(now()->year, now()->year - 5);
    $fmt = fn ($value, $type) => match ($type) {
        'money' => $value === '' || $value === null ? '' : number_format((int) $value),
        'number' => $value === '' || $value === null ? '' : number_format((int) $value),
        default => $value,
    };
@endphp

@section('content')
    <x-page-header :title="__($types[$type][0])" :subtitle="__($types[$type][1])" :back="route('reports.index')">
        @if ($report)
            <x-slot:actions>
                <x-button variant="secondary" :href="route('reports.download', [$type, 'xlsx'] + $query)" icon="download">Excel</x-button>
                <x-button :href="route('reports.download', [$type, 'pdf'] + $query)" icon="download">PDF</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Report switcher --}}
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="flex w-max gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200">
            @foreach ($types as $key => [$title])
                <a href="{{ route('reports.show', [$key] + $period->query()) }}"
                    class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $type === $key ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __($title) }}</a>
            @endforeach
        </nav>
    </div>

    <x-card class="mb-6">
        <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" x-data="{ kind: '{{ $period->type }}' }">
            @if ($type === 'member')
                <div class="sm:col-span-2">
                    <x-select :label="__('Mwanachama')" name="member" :options="$members" :value="$member?->id" placeholder="Chagua mwanachama" onchange="this.form.submit()" />
                </div>
            @endif
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ __('Kipindi') }}</span>
                <select name="type" x-model="kind" class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                    @foreach (ReportPeriod::TYPES as $k => $label) <option value="{{ $k }}">{{ __($label) }}</option> @endforeach
                </select>
            </label>
            <label class="block" x-show="kind === 'month'">
                <span class="text-sm font-medium text-slate-700">{{ __('Mwezi') }}</span>
                <select name="month" :disabled="kind !== 'month'" class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                    @foreach ($monthNames as $m => $name) <option value="{{ $m }}" @selected($period->type === 'month' ? $period->number === $m : now()->month === $m)>{{ $name }}</option> @endforeach
                </select>
            </label>
            <label class="block" x-show="kind === 'quarter'" x-cloak>
                <span class="text-sm font-medium text-slate-700">{{ __('Robo') }}</span>
                <select name="quarter" :disabled="kind !== 'quarter'" class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                    @foreach ([1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]] as $q => [$from, $to])
                        <option value="{{ $q }}" @selected($period->type === 'quarter' ? $period->number === $q : now()->quarter === $q)>{{ __('Robo :number', ['number' => $q]) }} ({{ \Carbon\Carbon::create(2000, $from)->translatedFormat('M') }} – {{ \Carbon\Carbon::create(2000, $to)->translatedFormat('M') }})</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ __('Mwaka') }}</span>
                <select name="year" class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-emerald-600">
                    @foreach ($years as $y) <option value="{{ $y }}" @selected($period->year === $y)>{{ $y }}</option> @endforeach
                </select>
            </label>
            <x-button icon="search">{{ __('Onyesha') }}</x-button>
        </form>
    </x-card>

    @if (! $report)
        <x-card><x-empty icon="users" :title="__('Chagua mwanachama')" :text="__('Ripoti ya mwanachama inaonyesha ada zake, viingilio na malipo yake kwa kipindi ulichochagua.')" /></x-card>
    @else
        <div class="mb-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ $report->title }}</h2>
            <p class="text-sm text-slate-500">{{ $report->subtitle }}</p>
        </div>

        <div class="mb-6 mt-4 grid gap-3 sm:grid-cols-2 {{ count($report->summary) === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
            @foreach ($report->summary as $label => $value)
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                    <p class="mt-1 truncate text-lg font-semibold text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="space-y-6">
            @foreach ($report->sections as $section)
                <x-card :title="$section['title']" :padded="false">
                    @if (! $section['rows'])
                        <x-empty icon="chart" :title="__('Hakuna taarifa kwa kipindi hiki')" />
                    @else
                        <x-table>
                            <x-slot:head>
                                <tr>
                                    @foreach ($section['columns'] as [$label, $colType])
                                        <th class="whitespace-nowrap px-5 py-3 {{ $colType !== 'text' ? 'text-right' : '' }}">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </x-slot:head>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    @foreach ($section['columns'] as $key => [, $colType])
                                        <td class="whitespace-nowrap px-5 py-2.5 {{ $colType !== 'text' ? 'text-right tabular-nums' : '' }} {{ $loop->first ? 'font-medium text-slate-900' : '' }}">{{ $fmt($row[$key] ?? '', $colType) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            @if ($section['totals'])
                                <tr class="bg-emerald-50 font-semibold text-slate-900">
                                    @foreach ($section['columns'] as $key => [, $colType])
                                        <td class="whitespace-nowrap px-5 py-3 {{ $colType !== 'text' ? 'text-right tabular-nums' : '' }}">{{ $fmt($section['totals'][$key] ?? '', $colType) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </x-table>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
