<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_order_id')->constrained()->cascadeOnDelete();
            // Reference only, kept for admin traceability — every field
            // needed to redisplay this line item is snapshotted below,
            // independent of these, so a later catalog edit or delete
            // never changes a historical order.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('group');
            $table->string('category');
            $table->string('variant_label')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
