<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('payer_member_id')->constrained('members');
            $table->foreignId('credited_member_id')->constrained('members');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('channel')->default('ussd');
            $table->string('payer_msisdn', 20)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('payment_intent_id')->nullable()->unique()->constrained();
            $table->string('provider');
            $table->string('provider_transaction_id');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->string('verification_method');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at');
            $table->string('status')->default('posted');
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('credited_member_id')->constrained('members');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->foreignId('reversal_of_id')->nullable()->constrained('allocations');
            $table->timestamps();

            $table->index(['group_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_intents');
    }
};
