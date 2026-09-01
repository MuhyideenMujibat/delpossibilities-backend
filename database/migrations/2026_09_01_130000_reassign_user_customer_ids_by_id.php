<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The first pass assigned sequential codes; switch every account to the
// simpler id-based form (DEL-<signup year>-<zero-padded id>). Safe to run on
// a database that already has the id-based codes — it just re-sets the same
// values. One atomic UPDATE avoids any mid-flight unique-constraint clash.
return new class extends Migration
{
    public function up(): void
    {
        // Clear first so MySQL never sees a row being set to a value another
        // row still holds mid-statement.
        DB::table('users')->update(['customer_id' => null]);
        DB::table('users')->update([
            'customer_id' => DB::raw("CONCAT('DEL-', COALESCE(YEAR(created_at), YEAR(NOW())), '-', LPAD(id, 4, '0'))"),
        ]);
    }

    public function down(): void
    {
        // No-op: there's no meaningful previous value to restore to.
    }
};
