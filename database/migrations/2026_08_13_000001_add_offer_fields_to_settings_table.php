<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('offer_active')->default(false)->after('price_per_kg');
            $table->string('offer_title')->nullable()->after('offer_active');
            $table->text('offer_message')->nullable()->after('offer_title');
            $table->decimal('offer_price_per_kg', 10, 2)->nullable()->after('offer_message');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'offer_active',
                'offer_title',
                'offer_message',
                'offer_price_per_kg',
            ]);
        });
    }
};
