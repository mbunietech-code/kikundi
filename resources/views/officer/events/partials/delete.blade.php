{{-- Delete an event. With contributions, only the administrator, with a reason. --}}
@if (! $hasMoney || auth()->user()->can('manage-system'))
    <form method="POST" action="{{ route('events.destroy', $event) }}" x-data
        @submit="
            @if ($hasMoney)
                const r = prompt(@js(__('Mchango huu una malipo ya :amount. Ukiufuta, malipo yake yote yatafutwa. Andika sababu:', ['amount' => \App\Support\Money::format($totals[$event->id] ?? $total ?? 0)])));
                if (! r || r.trim().length < 5) { $event.preventDefault(); if (r !== null) alert(@js(__('Andika sababu ya herufi 5 au zaidi.'))); } else { $el.reason.value = r }
            @else
                if (! confirm(@js(__('Futa mchango ":title"?', ['title' => $event->title])))) $event.preventDefault()
            @endif
        ">
        @csrf @method('DELETE')
        <input type="hidden" name="reason">
        @if ($compact)
            <x-button size="sm" variant="ghost" class="text-rose-600">{{ __('Futa') }}</x-button>
        @else
            <x-button variant="secondary" icon="trash" class="text-rose-700">{{ __('Futa') }}</x-button>
        @endif
    </form>
@endif
