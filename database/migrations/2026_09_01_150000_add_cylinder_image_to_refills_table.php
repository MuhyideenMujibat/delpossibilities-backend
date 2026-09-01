<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Subscription refills now carry a cylinder photo, snapshotted at request
// time exactly like a normal gas Order — so the rider (and the admin refills
// list) can identify the cylinder for that delivery.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->string('cylinder_image')->nullable()->after('product_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->dropColumn('cylinder_image');
        });
    }
};
