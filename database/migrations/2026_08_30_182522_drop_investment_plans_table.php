<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fixed investment tiers are gone — replaced by a calculator driven by
// Setting::investment_minimum_amount/investment_monthly_rate_percent/
// investment_tenures_months (see the two migrations just before this one).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('investment_plans');
    }

    public function down(): void
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('term_months');
            $table->decimal('monthly_return_percent', 5, 2);
            $table->decimal('min_amount', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
