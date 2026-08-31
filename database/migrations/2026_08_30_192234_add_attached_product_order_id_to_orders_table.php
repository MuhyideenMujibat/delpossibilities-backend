<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A cart that was ALREADY PAID FOR standalone and is now just
            // riding along on this delivery for fulfilment — kept distinct
            // from product_order_id (a still-unpaid cart bundled into this
            // order's own Paystack charge) so a student can do BOTH on one
            // refill: attach their paid cart AND bundle their remaining
            // unpaid items. Same nullOnDelete + nullable-unique reasoning as
            // product_order_id: the gas order is the primary paid record and
            // must survive a (hypothetical) ProductOrder deletion, and one
            // paid cart can only be attached to a single delivery.
            $table->foreignId('attached_product_order_id')->nullable()->after('product_order_id')
                ->constrained('product_orders')->nullOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attached_product_order_id');
        });
    }
};
