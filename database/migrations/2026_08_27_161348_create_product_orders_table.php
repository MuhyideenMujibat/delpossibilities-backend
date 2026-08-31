<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('subtotal', 10, 2);
            // eazy_market-tier fee only — gas_services items are always
            // free delivery, and this never carries the gas-refill
            // on/off-campus fee (see ProductOrder::eazyMarketFeeForSubtotal).
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            // Delivery destination only — defaulted from the student's
            // profile at creation time, never consulted for fee math.
            $table->string('hostel_address');
            $table->enum('location_type', ['hostel', 'off_campus'])->default('hostel');
            $table->enum('status', ['pending', 'approved', 'picked_up', 'delivered', 'cancelled'])->default('pending');
            $table->string('paystack_reference')->nullable();
            $table->text('payment_authorization_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
