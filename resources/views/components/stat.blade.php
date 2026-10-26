@props(['label', 'value', 'hint' => null, 'icon' => 'chart', 'tone' => 'emerald', 'href' => null])
@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'rose' => 'bg-rose-50 text-rose-700',
        'violet' => 'bg-violet-50 text-violet-700',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition {{ $href ? 'hover:border-emerald-300 hover:shadow' : '' }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 truncate text-2xl font-semibold text-slate-900">{{ $value }}</p>
        </div>
        <span class="rounded-xl p-2.5 {{ $tones[$tone] ?? $tones['emerald'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
    @if ($hint)
        <p class="mt-3 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
