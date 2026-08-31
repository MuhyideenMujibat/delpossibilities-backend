<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Null/0 effectively turns referrals off (no reward to grant,
            // see User::grantReferralRewardIfEligible) — no default needed.
            $table->decimal('referral_reward_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('referral_reward_amount');
        });
    }
};
