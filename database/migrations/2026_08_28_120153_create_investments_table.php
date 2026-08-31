<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// No Paystack/in-app payment collection here — money moves by bank transfer
// outside the app (see Settings' investment_* bank fields), an admin
// manually confirms it arrived, and only then is a contract generated for
// the investor to sign in-app. See Investment status lifecycle:
// pending -> payment_confirmed (contract generated) -> signed, or
// -> cancelled at any point before signed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            // Snapshotted at submission so the contract text stays accurate
            // even if the investor later edits their profile — same
            // reasoning as ProductOrderItem's product snapshot.
            $table->string('investor_name');
            $table->string('investor_email');
            $table->string('investor_phone');
            $table->enum('status', ['pending', 'payment_confirmed', 'signed', 'cancelled'])->default('pending');
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->string('contract_path')->nullable();
            $table->string('signature_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
