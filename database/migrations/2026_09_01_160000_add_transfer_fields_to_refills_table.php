<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// When a subscription with a still-pending refill is transferred and the
// sender chooses to KEEP that refill, it stays behind as their own delivery:
// its kg is reserved out of the pool up front (kg_prereserved, so it isn't
// deducted again on delivery), and the sender's contact is snapshotted here
// since the subscriber's own recipient_* now points at the new owner.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('cylinder_image');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->boolean('kg_prereserved')->default(false)->after('recipient_phone');
        });
    }

    public function down(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_phone', 'kg_prereserved']);
        });
    }
};
