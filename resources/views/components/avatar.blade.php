@props(['member', 'size' => 'h-9 w-9 text-sm'])
@if ($member->photo_path)
    {{-- Members see their own photo through the portal; officers through the members area. --}}
    <img src="{{ request()->is('mwanachama*') ? route('member.photo') : route('members.photo', $member) }}?v={{ $member->updated_at?->timestamp }}" alt="" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size flex shrink-0 items-center justify-center rounded-full font-semibold ".($member->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500')]) }}>{{ mb_substr($member->full_name, 0, 1) }}</span>
@endif
