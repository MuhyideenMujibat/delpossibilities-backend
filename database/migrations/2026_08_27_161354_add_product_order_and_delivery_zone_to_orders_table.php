<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // nullOnDelete, not cascadeOnDelete: a ProductOrder is never
            // actually deleted in practice (no destroy endpoint exists),
            // but if it ever were, the gas order it's bundled with is the
            // paid, primary record — it must survive with just a lost
            // "bundled with" reference, not be deleted as collateral
            // damage. unique() (nullable-unique) prevents the same
            // ProductOrder from ever being linked to two different gas
            // orders (e.g. a raced double-submit).
            $table->foreignId('product_order_id')->nullable()->after('delivery_fee')
                ->constrained('product_orders')->nullOnDelete()->unique();

            // Reference only, for "which zone" display on order detail
            // views — the fee itself is already snapshotted into
            // orders.delivery_fee, exactly as it works today. nullOnDelete
            // so an admin deleting a zone later never cascades into
            // deleting historical orders.
            $table->foreignId('delivery_zone_id')->nullable()->after('location_type')
                ->constrained('delivery_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_order_id');
            $table->dropConstrainedForeignId('delivery_zone_id');
        });
    }
};
