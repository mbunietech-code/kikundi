<?php

use App\Models\Member;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('mjeshi:create-admin {email} {name}', function (string $email, string $name) {
    $password = $this->secret('Nenosiri');

    User::query()->updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $password,
        'role' => 'system_admin',
    ]);

    $this->info("System admin {$email} yuko tayari.");
})->purpose('Create or update a system administrator');

Artisan::command('mjeshi:set-pin {group_id} {member_number}', function (int $groupId, string $memberNumber) {
    $member = Member::query()->where('group_id', $groupId)->where('member_number', $memberNumber)->firstOrFail();
    $pin = $this->secret('PIN mpya (tarakimu 4)');

    if (! preg_match('/^\d{4}$/', (string) $pin)) {
        $this->error('PIN lazima iwe tarakimu 4.');

        return 1;
    }

    $member->update(['pin_hash' => Hash::make($pin), 'pin_failed_attempts' => 0, 'pin_locked_until' => null]);
    $this->info("PIN ya {$member->full_name} imewekwa.");
})->purpose('Set a member USSD PIN');

Artisan::command('mjeshi:verify-payment {reference} {transaction_id} {--provider=manual}', function (string $reference, string $transactionId, VerifiedPaymentService $service) {
    $intent = PaymentIntent::query()->where('reference', $reference)->firstOrFail();

    if (! $this->confirm("Thibitisha {$intent->amount} {$intent->currency} kwa {$reference} (muamala {$transactionId})?")) {
        return 1;
    }

    $payment = $service->post($intent, $this->option('provider'), $transactionId, (int) $intent->amount, 'manual');
    $this->info("Malipo #{$payment->id} yamerekodiwa.");
})->purpose('Post a verified receipt for a pending payment intent');
