<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eazy_market_delivery_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_amount', 10, 2);
            $table->decimal('max_amount', 10, 2)->nullable(); // null = "and above"
            $table->decimal('fee', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eazy_market_delivery_tiers');
    }
};
