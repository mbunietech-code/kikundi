<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Member;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(): View
    {
        $groupId = $this->groupId();

        $totals = Allocation::query()
            ->where('group_id', $groupId)
            ->where('target_type', 'savings')
            ->selectRaw('credited_member_id, SUM(amount) as total, MAX(created_at) as last_at')
            ->groupBy('credited_member_id')
            ->get()
            ->keyBy('credited_member_id');

        $members = Member::query()->where('group_id', $groupId)->orderBy('member_number')->get()
            ->map(fn (Member $m) => [
                'member' => $m,
                'total' => (int) ($totals[$m->id]->total ?? 0),
                'last_at' => $totals[$m->id]->last_at ?? null,
            ])
            ->sortByDesc('total')
            ->values();

        $recent = Allocation::query()->where('group_id', $groupId)->where('target_type', 'savings')
            ->latest()->take(8)->get();

        return view('officer.savings.index', [
            'rows' => $members,
            'total' => $members->sum('total'),
            'savers' => $members->where('total', '>', 0)->count(),
            'recent' => $recent,
            'memberNames' => Member::query()->where('group_id', $groupId)->pluck('full_name', 'id'),
        ]);
    }
}
