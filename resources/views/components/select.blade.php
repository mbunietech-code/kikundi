@props(['label', 'name', 'options' => [], 'value' => null, 'placeholder' => null])
<label class="block">
    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
    <select name="{{ $name }}" {{ $attributes->merge(['class' => 'mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-emerald-600 '.($errors->has($name) ? 'ring-rose-400' : 'ring-slate-300')]) }}>
        @if ($placeholder) <option value="">{{ __($placeholder) }}</option> @endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ __($text) }}</option>
        @endforeach
    </select>
    @error($name) <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
</label>
