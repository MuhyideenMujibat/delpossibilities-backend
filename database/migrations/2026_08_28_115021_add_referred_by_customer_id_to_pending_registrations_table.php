<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            // Registration is staged (see PendingRegistration) — the raw
            // code entered at signup rides through here until
            // AuthController::verifyRegistration resolves it to a real
            // users.referred_by_user_id on account creation.
            $table->string('referred_by_customer_id')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('referred_by_customer_id');
        });
    }
};
