<?php

use App\Integrations\AzamPay\AzamPayClient;
use App\Integrations\AzamPay\MobileNetwork;
use App\Models\Group;
use App\Models\Member;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Membership\MembershipService;
use App\Services\Payments\VerifiedPaymentService;
use App\Services\Reminders\ReminderService;
use App\Support\Phone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mjeshi:create-admin {email} {name}', function (string $email, string $name) {
    $password = $this->secret(__('Nenosiri'));

    User::query()->updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $password,
        'role' => 'system_admin',
    ]);

    $this->info(__('Msimamizi :email yuko tayari.', ['email' => $email]));
})->purpose('Create or update a system administrator');

Artisan::command('mjeshi:set-pin {group_id} {member_number}', function (int $groupId, string $memberNumber) {
    $member = Member::query()->where('group_id', $groupId)->where('member_number', $memberNumber)->firstOrFail();
    $pin = $this->secret(__('PIN mpya (tarakimu 4)'));

    if (! preg_match('/^\d{4}$/', (string) $pin)) {
        $this->error(__('PIN lazima iwe tarakimu 4.'));

        return 1;
    }

    $member->update(['pin_hash' => Hash::make($pin), 'pin_failed_attempts' => 0, 'pin_locked_until' => null]);
    $this->info(__('PIN ya :name imewekwa.', ['name' => $member->full_name]));
})->purpose('Set a member USSD PIN');

Artisan::command('mjeshi:verify-payment {reference} {transaction_id} {--provider=manual}', function (string $reference, string $transactionId, VerifiedPaymentService $service) {
    $intent = PaymentIntent::query()->where('reference', $reference)->firstOrFail();

    if (! $this->confirm(__('Thibitisha :amount :currency kwa :reference (muamala :transaction)?', ['amount' => $intent->amount, 'currency' => $intent->currency, 'reference' => $reference, 'transaction' => $transactionId]))) {
        return 1;
    }

    $payment = $service->post($intent, $this->option('provider'), $transactionId, (int) $intent->amount, 'manual');
    $this->info(__('Malipo #:id yamerekodiwa.', ['id' => $payment->id]));
})->purpose('Post a verified receipt for a pending payment intent');

Artisan::command('mjeshi:enforce-membership', function (MembershipService $membership) {
    foreach (Group::query()->where('status', 'active')->get() as $group) {
        $count = $membership->enforce($group->id);
        $this->info(__(':group: wanachama :count wamekuwa si hai.', ['group' => $group->name, 'count' => $count]));
    }
})->purpose('Mark members with too many unpaid months as not active');

// Needs "php artisan schedule:run" every minute (Windows Task Scheduler on WAMP).
Schedule::command('mjeshi:enforce-membership')->dailyAt('06:00');

Artisan::command('mjeshi:send-reminders', function (ReminderService $reminders) {
    foreach (Group::query()->where('status', 'active')->get() as $group) {
        $sent = $reminders->runDaily($group->id);
        $this->info(__(':group: ukumbusho wa ada :dues, ada iliyochelewa :overdue, michango :events.', ['group' => $group->name, 'dues' => $sent['dues_reminders'], 'overdue' => $sent['dues_overdue'], 'events' => $sent['event_reminders']]));
    }
})->purpose('Create the current month dues and send due SMS reminders');

Schedule::command('mjeshi:send-reminders')->dailyAt('08:00');

Artisan::command('mjeshi:backup', function (BackupService $backups) {
    $name = $backups->create();
    $removed = $backups->prune();
    $this->info(__('Nakala imehifadhiwa: :path. Za zamani zilizofutwa: :removed.', ['path' => 'storage/app/private/'.$name, 'removed' => $removed]));
})->purpose('Back up the database and member photos, and remove old backups');

Schedule::command('mjeshi:backup')->dailyAt('01:00');

// On WAMP there is no always-on worker: send queued SMS from the scheduler.
// Each run stops when the queue is empty or before the next minute starts.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

Artisan::command('mjeshi:azampay-check {--msisdn= : Also send a test push to this number} {--amount=1000}', function (AzamPayClient $azampay) {
    if (! $azampay->configured()) {
        $this->error(__('Weka AZAMPAY_APP_NAME, AZAMPAY_CLIENT_ID na AZAMPAY_CLIENT_SECRET kwenye .env kwanza.'));

        return 1;
    }

    $azampay->token();
    $this->info(__('AzamPay (:environment): tokeni imepatikana.', ['environment' => config('azampay.environment')]));

    if ($msisdn = $this->option('msisdn')) {
        $number = Phone::normalise($msisdn);
        $provider = MobileNetwork::provider($number);

        if (! $provider) {
            $this->error(__('Mtandao wa namba :number haujulikani.', ['number' => $number]));

            return 1;
        }

        $result = $azampay->mnoCheckout($number, (int) $this->option('amount'), 'TEST'.now()->format('His'), $provider);
        $this->info(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $this->line(__('Callback URL ya kusajili: :url', ['url' => filled(config('azampay.callback_token')) ? route('azampay.callback', config('azampay.callback_token')) : '(weka AZAMPAY_CALLBACK_TOKEN)']));
})->purpose('Check AzamPay credentials and optionally send a test push');
