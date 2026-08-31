<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Reference only — the plan can be edited or retired later
            // without touching an already-locked subscription, since the
            // price that matters is the locked_price snapshot below.
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->decimal('locked_price', 10, 2);
            $table->enum('status', ['pending', 'active', 'expired'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // Delivery recipient details — defaulted from the subscribing
            // user, then rewritten by /transfer when the customer_id is
            // handed to someone else, independent of the login account.
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('recipient_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
