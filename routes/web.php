<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DeletionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dev\UssdSimulatorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Member\MemberLoginController;
use App\Http\Controllers\Member\MemberPortalController;
use App\Http\Controllers\Officer\AnnouncementController;
use App\Http\Controllers\Officer\ContributionEventController;
use App\Http\Controllers\Officer\DashboardController;
use App\Http\Controllers\Officer\DependantController;
use App\Http\Controllers\Officer\DuesController;
use App\Http\Controllers\Officer\MemberController;
use App\Http\Controllers\Officer\MembershipController;
use App\Http\Controllers\Officer\PaymentController;
use App\Http\Controllers\Officer\ReminderController;
use App\Http\Controllers\Officer\ReportController;
use App\Http\Controllers\Officer\SavingsController;
use App\Http\Controllers\Officer\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Language switch for everyone, signed in or not.
Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');

// Try the USSD menu from a phone on the same Wi-Fi. Never available in production.
if (app()->environment('local', 'testing')) {
    Route::get('/ussd-simulator', [UssdSimulatorController::class, 'show'])->name('ussd-simulator');
    Route::post('/ussd-simulator', [UssdSimulatorController::class, 'send'])->middleware('throttle:120,1');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Member portal: phone number and USSD PIN.
Route::prefix('mwanachama')->name('member.')->group(function () {
    Route::middleware('guest:member')->group(function () {
        Route::get('ingia', [MemberLoginController::class, 'show'])->name('login');
        Route::post('ingia', [MemberLoginController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth:member')->group(function () {
        Route::get('/', [MemberPortalController::class, 'home'])->name('home');
        Route::get('taarifa.pdf', [MemberPortalController::class, 'statement'])->name('statement');
        Route::get('picha', [MemberPortalController::class, 'photo'])->name('photo');
        Route::get('pin', [MemberPortalController::class, 'editPin'])->name('pin');
        Route::put('pin', [MemberPortalController::class, 'updatePin'])->middleware('throttle:5,1');
        Route::post('toka', [MemberLoginController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth', 'can:officer'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Writes first, so members/create is matched before members/{member}.
    Route::middleware('can:manage-membership')->group(function () {
        Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::post('members/enforce', [MembershipController::class, 'enforce'])->name('members.enforce');
        Route::get('members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::put('members/{member}/pin', [MemberController::class, 'updatePin'])->name('members.pin');
        Route::post('members/{member}/reinstatement', [MembershipController::class, 'reinstate'])->name('members.reinstatement');
        Route::post('members/{member}/dependants', [DependantController::class, 'store'])->name('members.dependants.store');
        Route::delete('members/{member}/dependants/{dependant}', [DependantController::class, 'destroy'])->name('members.dependants.destroy');

        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('announcements/{announcement}/sms', [AnnouncementController::class, 'sms'])->name('announcements.sms');
    });

    Route::middleware('can:view-membership')->group(function () {
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('members/{member}/photo', [MemberController::class, 'photo'])->name('members.photo');
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    });

    Route::middleware('can:manage-events')->group(function () {
        Route::get('events/create', [ContributionEventController::class, 'create'])->name('events.create');
        Route::post('events', [ContributionEventController::class, 'store'])->name('events.store');
        Route::get('events/{event}/edit', [ContributionEventController::class, 'edit'])->name('events.edit');
        Route::put('events/{event}', [ContributionEventController::class, 'update'])->name('events.update');
    });
    Route::get('events', [ContributionEventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [ContributionEventController::class, 'show'])->name('events.show');

    Route::middleware('can:manage-finance')->group(function () {
        Route::post('dues/rule', [DuesController::class, 'storeRule'])->name('dues.rule');
        Route::post('dues/generate', [DuesController::class, 'generate'])->name('dues.generate');
        Route::post('reminders/run', [ReminderController::class, 'run'])->name('reminders.run');
        Route::post('reminders/debts', [ReminderController::class, 'debts'])->name('reminders.debts');

        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    });

    Route::middleware('can:view-finance')->group(function () {
        Route::get('dues', [DuesController::class, 'index'])->name('dues.index');
        Route::get('savings', [SavingsController::class, 'index'])->name('savings.index');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{type}/download/{format}', [ReportController::class, 'download'])->name('reports.download');
    });

    Route::middleware('can:manage-system')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/admin/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('/admin/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/admin/backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Route::get('/admin/backups/{file}', [BackupController::class, 'download'])->name('admin.backups.download');

        // Deleting money records: administrators only, with a reason, logged.
        Route::get('/admin/deletions', [DeletionController::class, 'index'])->name('admin.deletions.index');
        Route::delete('payments/{payment}', [DeletionController::class, 'payment'])->name('payments.destroy');
        Route::get('members/{member}/delete', [DeletionController::class, 'confirmMember'])->name('members.delete-with-payments');
        Route::delete('members/{member}/with-payments', [DeletionController::class, 'member'])->name('members.destroy-with-payments');
    });
});
