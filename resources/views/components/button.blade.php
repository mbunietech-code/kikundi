@props(['variant' => 'primary', 'href' => null, 'icon' => null, 'size' => 'md'])
@php
    $variants = [
        'primary' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-emerald-600',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
    ];
    $sizes = ['sm' => 'px-3 py-1.5 text-xs', 'md' => 'px-4 py-2 text-sm'];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '.($variants[$variant] ?? $variants['primary']).' '.$sizes[$size];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="h-4 w-4" /> @endif {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="h-4 w-4" /> @endif {{ $slot }}
    </button>
@endif
