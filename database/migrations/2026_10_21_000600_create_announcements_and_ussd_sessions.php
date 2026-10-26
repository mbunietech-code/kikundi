<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('msisdn', 20);
            $table->string('operator')->nullable();
            $table->foreignId('member_id')->nullable()->constrained();
            $table->string('state')->default('start');
            $table->json('data')->nullable();
            $table->boolean('authenticated')->default(false);
            $table->unsignedInteger('step')->default(0);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_sessions');
        Schema::dropIfExists('announcements');
    }
};
