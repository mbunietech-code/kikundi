<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_status_changes', function (Blueprint $table) {
            // System reasons are stored as a translation key plus these values,
            // so the history reads in whichever language the viewer chose.
            $table->json('reason_params')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('member_status_changes', function (Blueprint $table) {
            $table->dropColumn('reason_params');
        });
    }
};
