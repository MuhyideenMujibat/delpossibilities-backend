<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Refills now go through the same pending -> approved -> picked_up ->
// delivered pipeline as regular orders (see OrderController::updateStatus),
// instead of jumping straight from pending to delivered/cancelled. Raw SQL
// because modifying a MySQL enum's value list isn't something Schema::table
// ->change() can do without doctrine/dbal.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE refills MODIFY status ENUM('pending', 'approved', 'picked_up', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Collapse anything sitting in the two new intermediate statuses
        // back to pending first, so the narrower down() enum never rejects
        // an existing row's value.
        DB::statement("UPDATE refills SET status = 'pending' WHERE status IN ('approved', 'picked_up')");
        DB::statement("ALTER TABLE refills MODIFY status ENUM('pending', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
