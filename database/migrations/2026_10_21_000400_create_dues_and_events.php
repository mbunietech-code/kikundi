<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('dues_rule_id')->nullable()->constrained();
            $table->string('period', 7);
            $table->unsignedBigInteger('amount_due');
            $table->string('currency', 3)->default('TZS');
            $table->timestamps();

            $table->unique(['member_id', 'period']);
        });

        Schema::create('contribution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->string('event_type');
            $table->string('title');
            $table->foreignId('beneficiary_member_id')->nullable()->constrained('members');
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['group_id', 'event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_events');
        Schema::dropIfExists('obligations');
        Schema::dropIfExists('dues_rules');
    }
};
