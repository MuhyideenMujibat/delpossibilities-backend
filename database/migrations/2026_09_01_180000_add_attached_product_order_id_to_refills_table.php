<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Brings refills to parity with gas orders (see
// add_attached_product_order_id_to_orders_table): a subscriber can now do
// BOTH on one refill trip —
//   - product_order_id          : a cart built from the refill request and
//                                 paid for on its own right after
//   - attached_product_order_id : a cart ALREADY paid for standalone, just
//                                 tagged onto this delivery for fulfilment
// Same nullOnDelete + nullable-unique reasoning: one paid cart can only
// ride on a single delivery.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->foreignId('attached_product_order_id')->nullable()->after('product_order_id')
                ->constrained('product_orders')->nullOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attached_product_order_id');
        });
    }
};
