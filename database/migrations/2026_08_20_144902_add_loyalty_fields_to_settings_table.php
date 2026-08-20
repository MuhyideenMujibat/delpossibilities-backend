<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false)->after('offer_price_per_kg');
            $table->decimal('loyalty_threshold_kg', 8, 2)->nullable()->after('loyalty_enabled');
            $table->decimal('loyalty_discount_percent', 5, 2)->nullable()->after('loyalty_threshold_kg');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_enabled',
                'loyalty_threshold_kg',
                'loyalty_discount_percent',
            ]);
        });
    }
};
