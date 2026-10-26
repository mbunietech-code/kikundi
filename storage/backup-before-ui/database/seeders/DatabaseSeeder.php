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

        $amina = Member::query()->create([
            'group_id' => $group->id,
            'member_number' => 'M001',
            'full_name' => 'Amina Juma',
            'phone' => '255700000001',
            'joined_at' => now()->subYear(),
            'pin_hash' => Hash::make('1234'),
        ]);

        Member::query()->create([
            'group_id' => $group->id,
            'member_number' => 'M002',
            'full_name' => 'Baraka Mussa',
            'phone' => '255700000002',
            'joined_at' => now()->subYear(),
            'pin_hash' => Hash::make('1234'),
        ]);

        foreach ([now()->subMonth(), now()] as $month) {
            Obligation::query()->create([
                'group_id' => $group->id,
                'member_id' => $amina->id,
                'dues_rule_id' => $rule->id,
                'period' => $month->format('Y-m'),
                'amount_due' => $rule->amount,
            ]);
        }

        ContributionEvent::query()->create([
            'group_id' => $group->id,
            'event_type' => 'illness',
            'title' => 'Matibabu ya Mzee Hamisi',
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
