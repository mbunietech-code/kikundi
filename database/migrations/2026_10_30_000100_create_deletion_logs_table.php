<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A permanent record of deleted payments and members, kept after the
        // rows themselves are gone, so money never disappears without trace.
        Schema::create('deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('subject');
            $table->unsignedBigInteger('amount')->default(0);
            $table->json('details')->nullable();
            $table->string('reason');
            $table->timestamps();
            $table->index(['group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_logs');
    }
};
