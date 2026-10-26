@if ($paginator->hasPages())
    <nav class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
        <p class="text-slate-500">{{ __(':from–:to kati ya :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-1.5 text-slate-300 ring-1 ring-slate-200">{{ __('Nyuma') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg px-3 py-1.5 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('Nyuma') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg px-3 py-1.5 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('Mbele') }}</a>
            @else
                <span class="rounded-lg px-3 py-1.5 text-slate-300 ring-1 ring-slate-200">{{ __('Mbele') }}</span>
            @endif
        </div>
    </nav>
@endif
