<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Plain strings, not DB enums — same reasoning as
            // subscription_plans' package_type/tier: new categories can be
            // added as validated app-layer constants later without an
            // ALTER TABLE. See Product::GROUPS/CATEGORIES.
            $table->string('group');
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            // min:0 (not min:0.01 like subscription price) — cylinder
            // cleaning must support ₦0/free. Ignored at checkout time for
            // any product that has variants; the variant's own price
            // applies instead.
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
