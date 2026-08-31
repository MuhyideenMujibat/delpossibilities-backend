<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Self-referencing — set once, at registration, from the
            // subscriber whose Customer ID was entered (see
            // AuthController::verifyRegistration). nullOnDelete so
            // deleting the referrer account never cascades into deleting
            // everyone they referred.
            $table->foreignId('referred_by_user_id')->nullable()->after('user_type_id')
                ->constrained('users')->nullOnDelete();

            // Whether THIS user's referral has already paid out their
            // referrer — checked directly in
            // User::grantReferralRewardIfEligible() so the one-time reward
            // is correct regardless of whether a gas order or a
            // subscription is what triggers it first.
            $table->boolean('referral_reward_granted')->default(false)->after('referred_by_user_id');

            // This user's OWN redeemable balance, earned by referring
            // others — independent of referred_by_user_id above.
            $table->decimal('referral_credit_balance', 10, 2)->default(0)->after('referral_reward_granted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropColumn(['referral_reward_granted', 'referral_credit_balance']);
        });
    }
};
