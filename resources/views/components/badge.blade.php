@props(['tone' => 'slate'])
@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.($tones[$tone] ?? $tones['slate'])]) }}>{{ $slot }}</span>
