<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // Set from the plan's cylinder_kg at activation (see
            // Subscriber::activate()) — the whole point of "activeness"
            // going forward: a subscriber can request refills as long as
            // this is above 0, regardless of how many calendar days are
            // left in the session/semester.
            $table->decimal('remaining_kg', 8, 2)->nullable()->after('locked_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('remaining_kg');
        });
    }
};
