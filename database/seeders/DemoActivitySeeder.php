<?php

namespace Database\Seeders;

use App\Models\ContributionEvent;
use App\Models\Member;
use App\Services\Dues\DuesService;
use App\Services\Payments\PaymentIntentService;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Database\Seeder;

/**
 * Optional sample payments so the dashboard has something to show.
 * Run with: php artisan db:seed --class=DemoActivitySeeder
 */
class DemoActivitySeeder extends Seeder
{
    public function run(DuesService $dues, PaymentIntentService $intents, VerifiedPaymentService $verified): void
    {
        $members = Member::query()->orderBy('member_number')->get();
        $groupId = $members->first()->group_id;
        $dues->generate($groupId, now()->format('Y-m'));
        $event = ContributionEvent::query()->where('group_id', $groupId)->first();

        foreach ($members->skip(1) as $i => $member) {
            $obligation = $member->obligations()->where('period', now()->format('Y-m'))->first();
            $amount = $i % 2 ? 10000 : 6000;
            $tx = 'DEMO'.$member->member_number;

            $intent = $intents->create($member, $member, 'dues', $obligation->id, $amount, 'demo:dues:'.$member->id);
            $verified->post($intent, 'mpesa', $tx, $amount, 'manual');

            $saving = $intents->create($member, $member, 'savings', null, 5000 * $i, 'demo:savings:'.$member->id);
            $verified->post($saving, 'mpesa', $tx.'S', 5000 * $i, 'manual');
        }

        $gift = $intents->create($members[2], $members[2], 'event', $event->id, 20000, 'demo:event:1');
        $verified->post($gift, 'cash', 'RISITI-0001', 20000, 'manual');

        // Two USSD requests waiting for the treasurer.
        $intents->create($members[0], $members[0], 'dues', $members[0]->obligations()->orderBy('period')->first()->id, 10000, 'demo:pending:1', 'ussd', $members[0]->phone);
        $intents->create($members[0], $members[1], 'savings', null, 3000, 'demo:pending:2', 'ussd', $members[0]->phone);
    }
}
