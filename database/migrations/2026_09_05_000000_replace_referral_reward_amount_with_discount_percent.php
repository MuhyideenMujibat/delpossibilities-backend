<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// referral_reward_amount was leftover from the old naira-credit referral
// model (superseded by the flat-percent gas-discount coupon — see
// 2026_09_01_140000_switch_referral_to_gas_discount) and was never read
// anywhere. Repurposing the column so the admin can edit the referral
// coupon's discount percentage (User::REFERRAL_DISCOUNT_PERCENT stays as
// the fallback when this is null).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('referral_reward_amount');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('referral_discount_percent', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('referral_discount_percent');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('referral_reward_amount', 10, 2)->nullable();
        });
    }
};
