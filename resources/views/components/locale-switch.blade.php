@props(['dark' => false])
{{-- Language switch; each option is written in its own language. --}}
<div {{ $attributes->merge(['class' => 'inline-flex rounded-lg p-0.5 text-xs font-semibold '.($dark ? 'bg-white/10' : 'bg-slate-100')]) }} role="group" aria-label="Language / Lugha">
    @foreach (\App\Support\Locale::SUPPORTED as $code => $name)
        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" title="{{ $name }}"
            @if (app()->getLocale() === $code) aria-current="true" @endif
            class="rounded-md px-2 py-1 uppercase transition {{ app()->getLocale() === $code
                ? ($dark ? 'bg-white text-emerald-800' : 'bg-white text-slate-900 shadow-sm')
                : ($dark ? 'text-emerald-100 hover:text-white' : 'text-slate-500 hover:text-slate-900') }}">{{ $code }}</a>
    @endforeach
</div>
