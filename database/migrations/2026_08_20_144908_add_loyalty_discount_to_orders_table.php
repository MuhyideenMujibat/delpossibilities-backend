<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('loyalty_discount_applied')->default(false)->after('price_per_kg');
            $table->decimal('loyalty_discount_amount', 10, 2)->nullable()->after('loyalty_discount_applied');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['loyalty_discount_applied', 'loyalty_discount_amount']);
        });
    }
};
