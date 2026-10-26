<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Dependant;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DependantController extends Controller
{
    public const RELATIONSHIPS = ['mke' => 'Mke', 'mume' => 'Mume', 'mtoto' => 'Mtoto', 'mzazi' => 'Mzazi', 'mengineyo' => 'Mengineyo'];

    public function store(Request $request, Member $member): RedirectResponse
    {
        $this->ensureGroup($member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', 'in:'.implode(',', array_keys(self::RELATIONSHIPS))],
        ], [], ['name' => __('jina'), 'relationship' => __('uhusiano')]);

        $member->dependants()->create($data + ['group_id' => $member->group_id]);

        return back()->with('status', __('Mtegemezi ameongezwa.'));
    }

    public function destroy(Member $member, Dependant $dependant): RedirectResponse
    {
        $this->ensureGroup($member);
        abort_unless($dependant->member_id === $member->id, 404);

        // Keep the record for history; mark as inactive instead of deleting.
        $dependant->update(['status' => 'inactive']);

        return back()->with('status', __('Mtegemezi ameondolewa.'));
    }
}
