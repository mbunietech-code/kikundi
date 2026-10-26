<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_events', function (Blueprint $table) {
            // Expected from each active member; blank means give what you can.
            $table->unsignedBigInteger('amount_per_member')->nullable()->after('description');
            $table->unsignedBigInteger('target_amount')->nullable()->after('amount_per_member');
        });

        // One row per automatic SMS, so a reminder is never sent twice.
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->string('key', 100);
            $table->timestamps();
            $table->unique(['member_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');

        Schema::table('contribution_events', function (Blueprint $table) {
            $table->dropColumn(['amount_per_member', 'target_amount']);
        });
    }
};
