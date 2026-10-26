<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('address');
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });

        // Entry and reinstatement fees; paid through allocations like dues.
        Schema::create('membership_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->string('kind');
            $table->unsignedBigInteger('amount_due');
            $table->string('currency', 3)->default('TZS');
            $table->timestamps();
            $table->index(['member_id', 'kind']);
        });

        Schema::create('member_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_status_changes');
        Schema::dropIfExists('membership_fees');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'status_changed_at']);
        });
    }
};
