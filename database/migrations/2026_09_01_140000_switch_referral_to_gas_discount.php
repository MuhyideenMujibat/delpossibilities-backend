<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Referral rework: drop the delivery-fee credit model, move to flat 10%-off
// gas-order coupons.
//  - A student who registers with a referral code gets one coupon (10% off
//    their first gas order).
//  - Their referrer gets one coupon once that student pays for a gas order
//    of 3 kg or more (once per referred student — the referral_reward_granted
//    flag on the referred user still guards that).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('referral_discount_available')->default(0)->after('referral_credit_balance');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('referral_discount_amount', 10, 2)->default(0)->after('referral_credit_applied');
        });

        // Clean switch — the old naira credit is forfeited.
        DB::table('users')->update(['referral_credit_balance' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_discount_available');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('referral_discount_amount');
        });
    }
};
