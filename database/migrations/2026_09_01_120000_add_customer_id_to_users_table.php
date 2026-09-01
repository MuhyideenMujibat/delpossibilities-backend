<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->unique()->after('phone');
        });

        // Backfill every existing account. The code is just the account's own
        // id, zero-padded, scoped by signup year — DEL-2026-0042 — so it's
        // guaranteed unique with no counting. Clear first, then fill, so MySQL
        // never sees a row being set to a value another row still holds.
        DB::table('users')->update(['customer_id' => null]);
        DB::table('users')->update([
            'customer_id' => DB::raw("CONCAT('DEL-', COALESCE(YEAR(created_at), YEAR(NOW())), '-', LPAD(id, 4, '0'))"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
