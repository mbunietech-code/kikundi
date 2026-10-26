<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0f172a; }
        .brand { color: #047857; font-weight: bold; font-size: 11px; }
        h1 { font-size: 17px; margin: 4px 0 2px; }
        .sub { color: #64748b; margin: 0 0 14px; }
        .summary { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 16px; }
        .summary td { background: #f1f5f9; border-radius: 6px; padding: 7px 9px; vertical-align: top; }
        .summary .label { color: #64748b; font-size: 8.5px; }
        .summary .value { font-weight: bold; font-size: 12px; margin-top: 2px; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #059669; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data tr.total td { font-weight: bold; background: #ecfdf5; border-top: 1.5px solid #059669; }
        table.data .num { text-align: right; white-space: nowrap; }
        .empty { color: #64748b; padding: 10px 6px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #94a3b8; font-size: 8px; }
    </style>
</head>
<body>
    <div class="footer">{{ $systemName }} · {{ __('Imetolewa :date', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    <div class="brand">{{ $systemName }}</div>
    <h1>{{ $report->title }}</h1>
    <p class="sub">{{ $report->subtitle }}</p>

    @if ($report->summary)
        <table class="summary"><tr>
            @foreach ($report->summary as $label => $value)
                <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr></table>
    @endif

    @foreach ($report->sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="data">
            <thead><tr>
                @foreach ($section['columns'] as [$label, $type])
                    <th class="{{ $type !== 'text' ? 'num' : '' }}">{{ $label }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($section['columns'] as $key => [, $type])
                            <td class="{{ $type !== 'text' ? 'num' : '' }}">{{ $type === 'money' ? number_format((int) ($row[$key] ?? 0)) : ($type === 'number' ? number_format((int) ($row[$key] ?? 0)) : ($row[$key] ?? '')) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($section['columns']) }}">{{ __('Hakuna taarifa kwa kipindi hiki.') }}</td></tr>
                @endforelse
                @if ($section['totals'] && $section['rows'])
                    <tr class="total">
                        @foreach ($section['columns'] as $key => [, $type])
                            @php $v = $section['totals'][$key] ?? ''; @endphp
                            <td class="{{ $type !== 'text' ? 'num' : '' }}">{{ $type !== 'text' && $v !== '' ? number_format((int) $v) : $v }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach
</body>
</html>
