<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cumulative kg bought since the student's last redeemed loyalty
            // discount. Resets to 0 whenever a reward is redeemed.
            $table->decimal('loyalty_progress_kg', 8, 2)->default(0)->after('cylinder_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('loyalty_progress_kg');
        });
    }
};
