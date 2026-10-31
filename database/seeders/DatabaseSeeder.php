<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ContributionEvent;
use App\Models\DuesRule;
use App\Models\Group;
use App\Models\Member;
use App\Models\Obligation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fabricated pilot data for local testing only. Never seed production.
 * Members M001 and M002 keep fixed phones and PIN 1234 for USSD tests.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $group = Group::query()->create(['name' => 'Kikundi cha Majaribio']);

        $rule = DuesRule::query()->create([
            'group_id' => $group->id,
            'amount' => 10000,
            'effective_from' => now()->startOfYear(),
        ]);

        $people = [
            ['M001', 'Amina Juma', '255700000001'],
            ['M002', 'Baraka Mussa', '255700000002'],
            ['M003', 'Neema Mollel', '255700000003'],
            ['M004', 'Hamisi Said', '255700000004'],
            ['M005', 'Rehema Kweka', '255700000005'],
            ['M006', 'Joseph Mrema', '255700000006'],
        ];

        $members = collect($people)->map(fn ($p, $i) => Member::query()->create([
            'group_id' => $group->id,
            'member_number' => $p[0],
            'full_name' => $p[1],
            'phone' => $p[2],
            'joined_at' => now()->subMonths(12 - $i),
            'pin_hash' => $i < 4 ? Hash::make('1234') : null,
        ]));

        // Amina has two unpaid months; the USSD tests rely on this.
        foreach ([now()->subMonthNoOverflow(), now()] as $month) {
            Obligation::query()->create([
                'group_id' => $group->id,
                'member_id' => $members[0]->id,
                'dues_rule_id' => $rule->id,
                'period' => $month->format('Y-m'),
                'amount_due' => $rule->amount,
            ]);
        }

        $members[0]->dependants()->create(['group_id' => $group->id, 'name' => 'Zawadi Juma', 'relationship' => 'mtoto']);

        ContributionEvent::query()->create([
            'group_id' => $group->id,
            'event_type' => 'illness',
            'title' => 'Matibabu ya Mzee Hamisi',
            'beneficiary_member_id' => $members[3]->id,
            'status' => 'active',
        ]);

        Announcement::query()->create([
            'group_id' => $group->id,
            'title' => 'Kikao',
            'body' => 'Kikao cha mwezi ni Jumamosi saa 4 asubuhi.',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
