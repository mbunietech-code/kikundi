<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberGuarantor;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarantors (wadhamini): fellow members or outsiders who vouch for a member.
 * The same rules apply on the registration form and on the member's page.
 */
class GuarantorController extends Controller
{
    /** Most a member can have; also the number of rows on the registration form. */
    public const MAX = 3;

    public function store(Request $request, Member $member): RedirectResponse
    {
        $this->ensureGroup($member);

        if ($member->guarantors()->count() >= self::MAX) {
            return back()->with('error', __('Mwanachama ana wadhamini :max tayari.', ['max' => self::MAX]));
        }

        $row = $this->validatedRows($request, $member)[0] ?? null;

        if (! $row) {
            return back()->withErrors(['guarantors' => __('Chagua mwanachama au andika jina na simu ya mdhamini.')]);
        }

        self::attach($member, $row);

        return back()->with('status', __('Mdhamini ameongezwa.'));
    }

    public function destroy(Member $member, MemberGuarantor $guarantor): RedirectResponse
    {
        $this->ensureGroup($member);
        abort_unless($guarantor->member_id === $member->id, 404);

        $guarantor->delete();

        return back()->with('status', __('Mdhamini ameondolewa.'));
    }

    /**
     * Validate the guarantors[] rows of a request, skipping fully empty rows.
     *
     * @return list<array{member_id: ?int, name: ?string, phone: ?string, relationship: ?string}>
     */
    public function validatedRows(Request $request, ?Member $member = null): array
    {
        $rows = collect($request->input('guarantors', []))
            ->map(fn ($row) => [
                'member_id' => filled($row['member_id'] ?? null) ? (int) $row['member_id'] : null,
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'phone' => filled($row['phone'] ?? null) ? Phone::normalise((string) $row['phone']) : null,
                'relationship' => trim((string) ($row['relationship'] ?? '')) ?: null,
            ])
            ->reject(fn ($row) => ! $row['member_id'] && ! $row['name'] && ! $row['phone'])
            ->values()
            ->all();

        $request->merge(['guarantors' => $rows]);

        $request->validate([
            'guarantors' => ['array', 'max:'.self::MAX],
            // A member may not vouch for themselves, and must be active to vouch at all.
            'guarantors.*.member_id' => ['nullable', 'distinct', Rule::notIn([$member?->id]),
                Rule::exists('members', 'id')->where('group_id', $this->groupId())->where('status', 'active')],
            'guarantors.*.name' => ['nullable', 'required_without:guarantors.*.member_id', 'string', 'max:120'],
            'guarantors.*.phone' => ['nullable', 'required_without:guarantors.*.member_id', 'regex:/^255[67]\d{8}$/'],
            'guarantors.*.relationship' => ['nullable', 'string', 'max:60'],
        ], [
            'guarantors.*.member_id.exists' => __('Mdhamini lazima awe mwanachama hai.'),
            'guarantors.*.member_id.not_in' => __('Mwanachama hawezi kujidhamini mwenyewe.'),
            'guarantors.*.member_id.distinct' => __('Mdhamini mmoja amechaguliwa mara mbili.'),
            'guarantors.*.phone.regex' => __('Weka namba sahihi ya simu, mfano 0712345678.'),
        ], [
            'guarantors' => __('wadhamini'),
            'guarantors.*.name' => __('jina la mdhamini'),
            'guarantors.*.phone' => __('simu ya mdhamini'),
            'guarantors.*.relationship' => __('uhusiano'),
        ]);

        return $rows;
    }

    /** Save one validated row; a fellow member's name and phone are copied in. */
    public static function attach(Member $member, array $row): MemberGuarantor
    {
        $guarantor = $row['member_id'] ? Member::query()->find($row['member_id']) : null;

        return $member->guarantors()->create([
            'group_id' => $member->group_id,
            'guarantor_member_id' => $guarantor?->id,
            'name' => $guarantor?->full_name ?? $row['name'],
            'phone' => $guarantor?->phone ?? $row['phone'],
            'relationship' => $row['relationship'],
        ]);
    }
}
