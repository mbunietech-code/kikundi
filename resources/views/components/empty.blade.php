@props(['icon' => 'chart', 'title', 'text' => null])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <span class="rounded-full bg-slate-100 p-3 text-slate-400"><x-icon :name="$icon" class="h-6 w-6" /></span>
    <p class="mt-3 text-sm font-medium text-slate-700">{{ $title }}</p>
    @if ($text) <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $text }}</p> @endif
    {{ $slot }}
</div>
