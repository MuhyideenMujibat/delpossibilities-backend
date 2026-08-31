<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A subscriber's refill never involves payment, so an Eazy Market/Gas
// Services cart attached to one still checks out (and gets charged)
// entirely on its own — this link is purely for fulfillment tracking: one
// delivery, one status, cascaded from the refill (see RefillController::update)
// so the student isn't left checking two disconnected pages for one trip.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->foreignId('product_order_id')->nullable()->after('subscriber_id')
                ->constrained('product_orders')->nullOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_order_id');
        });
    }
};
