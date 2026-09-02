<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Structured pickup/delivery times behind the free-text broadcast_message —
// up to two slots a day (e.g. a midday and an evening round), stored as
// "HH:MM" 24h strings so AdminSettings can repopulate its time inputs
// instead of trying to parse them back out of the composed sentence. The
// admin can use one slot, two, or leave both empty and hand-type
// broadcast_message directly; either way StudentHeader only ever reads
// broadcast_message.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('broadcast_slot1_pickup', 5)->nullable()->after('broadcast_message');
            $table->string('broadcast_slot1_delivery', 5)->nullable()->after('broadcast_slot1_pickup');
            $table->string('broadcast_slot2_pickup', 5)->nullable()->after('broadcast_slot1_delivery');
            $table->string('broadcast_slot2_delivery', 5)->nullable()->after('broadcast_slot2_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'broadcast_slot1_pickup',
                'broadcast_slot1_delivery',
                'broadcast_slot2_pickup',
                'broadcast_slot2_delivery',
            ]);
        });
    }
};
