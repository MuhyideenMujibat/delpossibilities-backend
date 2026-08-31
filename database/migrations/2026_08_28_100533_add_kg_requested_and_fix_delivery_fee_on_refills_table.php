<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A refill request now specifies its own kg (drawn down from the
// subscriber's remaining_kg balance at delivery time) instead of always
// implicitly being the plan's cylinder size — and refills are entirely
// covered by the upfront subscription payment, so the old flat ₦200
// per-refill delivery_fee no longer applies to anything.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->decimal('kg_requested', 8, 2)->nullable()->after('subscriber_id');
        });

        // Raw SQL to change the column default (matches the approach used
        // in 2026_08_27_141258_expand_status_on_refills_table.php) — no
        // longer 200, refills are free.
        DB::statement('ALTER TABLE refills MODIFY delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE refills MODIFY delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 200');

        Schema::table('refills', function (Blueprint $table) {
            $table->dropColumn('kg_requested');
        });
    }
};
