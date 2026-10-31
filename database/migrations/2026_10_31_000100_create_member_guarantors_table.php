<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who vouch for a member when they join (wadhamini). A guarantor
        // may be a fellow member or someone outside the group; name and phone
        // are kept either way so the record survives the guarantor leaving.
        Schema::create('member_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guarantor_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('relationship', 60)->nullable();
            $table->timestamps();
            $table->index('guarantor_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_guarantors');
    }
};
