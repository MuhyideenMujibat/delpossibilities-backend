<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A short, frequently-updated announcement (pickup/delivery time changes,
// closures, etc.) the admin can flip on/off without losing the last message
// they typed — shown as a scrolling strip to logged-in students (see
// StudentHeader.jsx). Read through the existing public GET /price endpoint
// (already returns the whole Setting row), so no new route is needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('broadcast_active')->default(false)->after('offer_price_per_kg');
            $table->string('broadcast_message', 500)->nullable()->after('broadcast_active');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['broadcast_active', 'broadcast_message']);
        });
    }
};
