@props(['status'])
@php
    $map = [
        'active' => ['Hai', 'emerald'],
        'inactive' => ['Sio mwanachama hai', 'rose'],
        'pending' => ['Inasubiri', 'amber'],
        'suspended' => ['Amesimamishwa', 'rose'],
        'departed' => ['Ameondoka', 'slate'],
        'succeeded' => ['Imethibitishwa', 'emerald'],
        'failed' => ['Imeshindwa', 'rose'],
        'expired' => ['Imeisha muda', 'slate'],
        'posted' => ['Imerekodiwa', 'emerald'],
        'draft' => ['Rasimu', 'slate'],
        'published' => ['Imechapishwa', 'emerald'],
        'closed' => ['Imefungwa', 'slate'],
        'cancelled' => ['Imeghairiwa', 'rose'],
        'paid' => ['Imelipwa', 'emerald'],
        'partial' => ['Sehemu', 'amber'],
        'unpaid' => ['Haijalipwa', 'rose'],
    ];
    [$label, $tone] = $map[$status] ?? [ucfirst((string) $status), 'slate'];
@endphp
<x-badge :tone="$tone">{{ __($label) }}</x-badge>
