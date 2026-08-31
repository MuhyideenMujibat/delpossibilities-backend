<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->timestamp('requested_at');
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('kg_delivered', 8, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(200);
            $table->enum('status', ['pending', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refills');
    }
};
