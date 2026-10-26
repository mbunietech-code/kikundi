<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_number');
            $table->string('full_name');
            $table->string('phone', 20)->index();
            $table->string('address')->nullable();
            $table->date('joined_at')->nullable();
            $table->string('status')->default('active');
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'member_number']);
        });

        Schema::create('dependants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->string('name');
            $table->string('relationship');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependants');
        Schema::dropIfExists('members');
    }
};
