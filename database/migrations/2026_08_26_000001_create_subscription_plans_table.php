<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            // Plain strings rather than DB enums: new tiers/package types can
            // be inserted as rows later (a new price plan, a "platinum" tier)
            // without an ALTER TABLE migration. Validated against a fixed
            // list at the application layer instead.
            $table->string('package_type');
            $table->string('tier');
            $table->decimal('cylinder_kg', 8, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('foodstuff_pack_value', 10, 2)->nullable();
            $table->boolean('has_souvenir')->default(false);
            $table->boolean('has_publicity')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
