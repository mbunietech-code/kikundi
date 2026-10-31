<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 20)->nullable()->unique()->after('id');
        });

        // Existing payments get numbers in the order they were recorded.
        DB::table('payments')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('payments')->where('id', $id)->update(['receipt_number' => sprintf('RCT%06d', $id)]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
