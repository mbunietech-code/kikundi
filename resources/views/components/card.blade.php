@props(['title' => null, 'padded' => true])
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
            @isset($actions) <div class="flex gap-2">{{ $actions }}</div> @endisset
        </header>
    @endif
    <div class="{{ $padded ? 'p-5' : '' }}">{{ $slot }}</div>
</section>
