<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Business-rule minimum of ₦300 is enforced in
            // AdminDeliveryZoneController's validation, not here — this
            // codebase never uses DB-level CHECK constraints, always
            // app-layer validation (matches subscription_plans, etc.).
            $table->decimal('fee', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
