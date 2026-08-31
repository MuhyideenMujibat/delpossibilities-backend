<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Free-form integer, not a fixed enum — more terms can be
            // added later as new rows without a migration, matching how
            // subscription_plans handles its package_type/tier taxonomy.
            $table->unsignedInteger('term_months');
            $table->decimal('monthly_return_percent', 5, 2);
            $table->decimal('min_amount', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_plans');
    }
};
