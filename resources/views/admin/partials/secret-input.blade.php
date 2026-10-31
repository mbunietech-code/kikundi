<div>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
        <input type="password" name="{{ $name }}" autocomplete="new-password"
            placeholder="{{ $saved ? __('•••••• imehifadhiwa — acha wazi kuitunza') : '' }}"
            class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset {{ $errors->has($name) ? 'ring-rose-400' : 'ring-slate-300' }} focus:ring-2 focus:ring-emerald-600">
    </label>
    @if ($hint ?? null) <span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span> @endif
    @error($name) <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
    @if ($saved)
        <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500">
            <input type="checkbox" name="clear[]" value="{{ $name }}" class="rounded border-slate-300 text-rose-600"> {{ __('Futa ufunguo uliohifadhiwa') }}
        </label>
    @endif
</div>
