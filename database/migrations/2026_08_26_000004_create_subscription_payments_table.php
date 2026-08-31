<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('paystack_reference')->unique()->nullable();
            // Lets pay() be idempotent (return the same in-flight checkout
            // instead of re-initializing with Paystack), same as
            // Order::payment_authorization_url.
            $table->string('payment_authorization_url')->nullable();
            $table->string('method')->default('paystack');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
