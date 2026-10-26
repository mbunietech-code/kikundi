@props(['label', 'name', 'type' => 'text', 'value' => null, 'hint' => null])
<label class="block">
    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
        {{ $attributes->merge(['class' => 'mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 '.($errors->has($name) ? 'ring-rose-400' : 'ring-slate-300')]) }}>
    @if ($hint) <span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span> @endif
    @error($name) <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
</label>
