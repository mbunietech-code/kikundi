<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-slate-100 text-sm']) }}>
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $head }}</thead>
        <tbody class="divide-y divide-slate-100 text-slate-700">{{ $slot }}</tbody>
    </table>
</div>
